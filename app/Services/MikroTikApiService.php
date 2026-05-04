<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Log;

class MikroTikApiService
{
    protected string $jumpHost;
    protected string $sshUser = 'admin';

    public function __construct()
    {
        $this->jumpHost = config('mikrotik.jump_host', 'support@103.137.141.8');
    }

    public function disconnectUser(string $username, Router $router): void
    {
        $mikrotikIp = $this->getMikroTikIp($router);

        $commands = [
            "/ip hotspot active remove [find where user='{$username}']",
        ];

        foreach ($commands as $cmd) {
            $this->runSsh($mikrotikIp, $cmd);
        }
    }

    public function removeUser(string $username, Router $router): void
    {
        $mikrotikIp = $this->getMikroTikIp($router);
        $this->runSsh($mikrotikIp, "/ip hotspot user remove [find where name='{$username}']");
    }

    public function getActiveUsers(Router $router): array
    {
        $mikrotikIp = $this->getMikroTikIp($router);
        $output = $this->runSsh($mikrotikIp, "/ip hotspot active print without-paging");

        $users = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/\d+\s+(\S+)\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
                $users[] = [
                    'user' => $m[1],
                    'address' => $m[2],
                ];
            }
        }

        return $users;
    }

    /**
     * Push konfigurasi hotspot ke MikroTik
     */
    public function setHotspotConfig(Router $router, int $sessionTimeout, int $idleTimeout, int $sharedUsers): void
    {
        $mikrotikIp = $this->getMikroTikIp($router);

        $this->runSsh($mikrotikIp, "/ip hotspot profile set [find] session-timeout={$sessionTimeout}");
        $this->runSsh($mikrotikIp, "/ip hotspot profile set [find] idle-timeout={$idleTimeout}");
        $this->runSsh($mikrotikIp, "/ip hotspot profile set [find] shared-users={$sharedUsers}");
    }

    /**
     * Ping MikroTik untuk cek koneksi
     */
    public function isReachable(Router $router): bool
    {
        $mikrotikIp = $this->getMikroTikIp($router);
        $output = $this->runSsh($mikrotikIp, ":put connected");
        return str_contains($output, 'connected');
    }

    /**
     * Dapatkan IP MikroTik dari tabel nas FreeRADIUS
     */
    protected function getMikroTikIp(Router $router): string
    {
        $nasIp = \Illuminate\Support\Facades\DB::table('nas')
            ->where('shortname', $router->nas_identifier)
            ->value('nasname');

        return $nasIp ?: ($router->hotspot_address ?: '10.0.70.4');
    }

    protected function runSsh(string $host, string $command): string
    {
        $escapedCmd = escapeshellarg($command);
        $hostEscaped = escapeshellarg($host);
        $jumpHost = escapeshellarg($this->jumpHost);

        $sshCmd = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=15 {$jumpHost} "
            . "'docker exec openvpn sshpass -p \"\" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 "
            . "{$this->sshUser}@{$hostEscaped} {$escapedCmd}' 2>&1";

        $output = [];
        $exitCode = 0;
        exec($sshCmd, $output, $exitCode);

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            Log::warning('MikroTik command failed', [
                'host' => $host,
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $result,
            ]);
        }

        return $result;
    }
}