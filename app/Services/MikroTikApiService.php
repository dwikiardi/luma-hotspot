<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Log;

class MikroTikApiService
{
    protected string $jumpHost;
    protected string $sshUser = 'admin';
    protected int $sshPort = 22;

    public function __construct()
    {
        $this->jumpHost = config('services.mikrotik.jump_host', 'support@103.137.141.8');
    }

    /**
     * Disconnect user aktif dari MikroTik hotspot
     */
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

    /**
     * Hapus semua session user tertentu
     */
    public function removeUser(string $username, Router $router): void
    {
        $mikrotikIp = $router->nas_ip ?? $router->hotspot_address ?? '10.0.70.4';

        $cmd = "/ip hotspot user remove [find where name='{$username}']";
        $this->runSsh($mikrotikIp, $cmd);
    }

    /**
     * Dapatkan daftar user aktif
     */
    public function getActiveUsers(Router $router): array
    {
        $mikrotikIp = $router->nas_ip ?? $router->hotspot_address ?? '10.0.70.4';
        $output = $this->runSsh($mikrotikIp, "/ip hotspot active print without-paging");

        $users = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/(\d+)\s+([^\s]+)\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
                $users[] = [
                    'user' => $m[2],
                    'address' => $m[3],
                    'uptime' => $m[1],
                ];
            }
        }

        return $users;
    }

    /**
     * Eksekusi SSH ke MikroTik melalui jump host
     */
    protected function runSsh(string $host, string $command): string
    {
        $escapedCmd = escapeshellarg($command);
        $jump = escapeshellarg($this->jumpHost);
        $user = escapeshellarg($this->sshUser);

        // SSH via jump host: container → Server B → MikroTik
        $sshCmd = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -J {$jump} {$user}@{$host} {$escapedCmd} 2>&1";

        $output = [];
        $exitCode = 0;
        exec($sshCmd, $output, $exitCode);

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            Log::warning('MikroTik SSH command failed', [
                'host' => $host,
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $result,
            ]);
        }

        return $result;
    }
}