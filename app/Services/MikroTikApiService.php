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
        $mikrotikIp = $router->nas_ip ?? $router->hotspot_address ?? '10.0.70.4';

        $commands = [
            "/ip hotspot active remove [find where user='{$username}']",
            "/ip hotspot user remove [find where name='{$username}']",
        ];

        foreach ($commands as $cmd) {
            $this->runSsh($mikrotikIp, $cmd);
        }
    }

    public function removeUser(string $username, Router $router): void
    {
        $mikrotikIp = $router->nas_ip ?? $router->hotspot_address ?? '10.0.70.4';
        $this->runSsh($mikrotikIp, "/ip hotspot user remove [find where name='{$username}']");
    }

    public function getActiveUsers(Router $router): array
    {
        $mikrotikIp = $router->nas_ip ?? $router->hotspot_address ?? '10.0.70.4';
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

    protected function runSsh(string $host, string $command): string
    {
        // Eksekusi SSH via Server B → Docker container → MikroTik
        $escapedCmd = escapeshellarg($command);
        $hostEscaped = escapeshellarg($host);

        $sshCmd = sprintf(
            'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=15 %s '
            . 'sg docker -c "docker exec openvpn sshpass -p \'\' ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 '
            . '%s@%s %s" 2>&1',
            escapeshellarg($this->jumpHost),
            escapeshellarg($this->sshUser),
            $hostEscaped,
            $escapedCmd
        );

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