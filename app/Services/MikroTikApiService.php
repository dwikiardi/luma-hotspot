<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Log;

class MikroTikApiService
{
    protected string $jumpHost;

    public function __construct()
    {
        $this->jumpHost = config('mikrotik.jump_host', 'support@103.137.141.8');
    }

    public function disconnectUser(string $username, Router $router): void
    {
        $mikrotikIp = $this->getMikroTikIp($router);
        $escapedUsername = escapeshellarg($username);
        $escapedIp = escapeshellarg($mikrotikIp);
        $jumpHost = escapeshellarg($this->jumpHost);

        $script = <<<PYEOF
from routeros_api import RouterOsApiPool
pool = RouterOsApiPool({$escapedIp}, username="admin", password="", plaintext_login=True, use_ssl=False)
api = pool.get_api()
active = api.get_resource("/ip/hotspot/active")
for e in active.get():
    if e.get("user") == {$escapedUsername}:
        rid = e.get(".id") or e.get("id")
        active.call("remove", {{".id": rid}})
pool.disconnect()
print("done")
PYEOF;

        $this->execPython($script);
    }

    public function getActiveUsers(Router $router): array
    {
        $mikrotikIp = $this->getMikroTikIp($router);
        $escapedIp = escapeshellarg($mikrotikIp);
        $jumpHost = escapeshellarg($this->jumpHost);

        $script = <<<PYEOF
from routeros_api import RouterOsApiPool
pool = RouterOsApiPool({$escapedIp}, username="admin", password="", plaintext_login=True, use_ssl=False)
api = pool.get_api()
active = api.get_resource("/ip/hotspot/active")
for e in active.get():
    print("=user=" + str(e.get("user","")) + "=address=" + str(e.get("address","")))
pool.disconnect()
PYEOF;

        $output = $this->execPython($script);
        $users = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/=user=(\S+)=address=(\S+)/', $line, $m)) {
                $users[] = ['user' => $m[1], 'address' => $m[2]];
            }
        }
        return $users;
    }

    public function setHotspotConfig(Router $router, int $sessionTimeout, int $idleTimeout, int $sharedUsers): void
    {
        $mikrotikIp = $this->getMikroTikIp($router);
        $escapedIp = escapeshellarg($mikrotikIp);
        $jumpHost = escapeshellarg($this->jumpHost);

        $script = <<<PYEOF
from routeros_api import RouterOsApiPool
pool = RouterOsApiPool({$escapedIp}, username="admin", password="", plaintext_login=True, use_ssl=False)
api = pool.get_api()
up = api.get_resource("/ip/hotspot/user/profile")
for p in up.get():
    rid = p.get(".id") or p.get("id")
    up.call("set", {{"shared-users": "{$sharedUsers}", "keepalive-timeout": "none", "idle-timeout": "none", ".id": rid}})
pool.disconnect()
print("done")
PYEOF;

        $this->execPython($script);
    }

    public function getHostByMac(Router $router, string $mac): ?array
    {
        if ($mac === 'unknown') return null;

        $mikrotikIp = $this->getMikroTikIp($router);
        $escapedIp = escapeshellarg($mikrotikIp);
        $escapedMac = escapeshellarg(strtoupper($mac));

        $script = <<<PYEOF
from routeros_api import RouterOsApiPool
pool = RouterOsApiPool({$escapedIp}, username="admin", password="", plaintext_login=True, use_ssl=False)
api = pool.get_api()
hosts = api.get_resource("/ip/hotspot/host")
for h in hosts.get():
    if h.get("mac-address","").upper() == {$escapedMac}:
        print("=user=" + str(h.get("user","")))
        print("=address=" + str(h.get("address","")))
        print("=authorized=" + str(h.get("authorized","")))
        print("=bypassed=" + str(h.get("bypassed","")))
pool.disconnect()
PYEOF;

        $output = $this->execPython($script);
        $result = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/=(\w+)=(.*)/', $line, $m)) {
                $result[$m[1]] = $m[2];
            }
        }
        return ! empty($result) ? $result : null;
    }

    public function isReachable(Router $router): boolRouter $router): bool
    {
        $mikrotikIp = $this->getMikroTikIp($router);
        $escapedIp = escapeshellarg($mikrotikIp);
        $jumpHost = escapeshellarg($this->jumpHost);

        $script = <<<PYEOF
from routeros_api import RouterOsApiPool
pool = RouterOsApiPool({$escapedIp}, username="admin", password="", plaintext_login=True, use_ssl=False)
api = pool.get_api()
res = api.get_resource("/system/resource")
for r in res.get():
    print(r.get("version",""))
pool.disconnect()
PYEOF;

        $output = $this->execPython($script);
        return !empty(trim($output));
    }

    protected function getMikroTikIp(Router $router): string
    {
        $nasIp = \Illuminate\Support\Facades\DB::table('nas')
            ->where('shortname', $router->nas_identifier)
            ->value('nasname');
        return $nasIp ?: ($router->hotspot_address ?: '10.0.70.4');
    }

    protected function execPython(string $script): string
    {
        $jumpHost = escapeshellarg($this->jumpHost);
        $encoded = base64_encode($script);

        $sshCmd = "ssh -i /var/www/.ssh/id_ed25519 -o UserKnownHostsFile=/dev/null "
            . "-o StrictHostKeyChecking=no -o ConnectTimeout=10 {$jumpHost} "
            . "'docker exec openvpn python3 -c \"import base64; exec(base64.b64decode(\\\"{$encoded}\\\"))\"' 2>&1";

        $output = [];
        $exitCode = 0;
        exec($sshCmd, $output, $exitCode);

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            Log::warning('MikroTik API failed', [
                'exit_code' => $exitCode,
                'output' => $result,
            ]);
        }

        return $result;
    }
}