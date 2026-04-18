<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MikroTikApiService
{
    protected ?string $ip;
    protected ?string $username;
    protected ?string $password;

    public function __construct(?string $ip = null, ?string $username = null, ?string $password = null)
    {
        $this->ip = $ip;
        $this->username = $username;
        $this->password = $password;
    }

    public static function forRouter(Router $router): self
    {
        return new self(
            $router->ip_address,
            config("services.mikrotik.username", "admin"),
            config("services.mikrotik.password", "")
        );
    }

    public function connect(): bool
    {
        if (! $this->ip || ! $this->username) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withoutVerifying()
                ->get("https://{$this->ip}/rest/login");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("MikroTik connection failed: " . $e->getMessage());
        }

        return false;
    }

    public function isOnline(): bool
    {
        return $this->connect();
    }

    public function getActiveHotspotUsers(): array
    {
        if (! $this->connect()) {
            return [];
        }

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withoutVerifying()
                ->get("https://{$this->ip}/rest/ip/hotspot/active");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("MikroTik get active users failed: " . $e->getMessage());
        }

        return [];
    }

    public function getResource(): array
    {
        if (! $this->connect()) {
            return [];
        }

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withoutVerifying()
                ->get("https://{$this->ip}/rest/system/resource");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("MikroTik get resource failed: " . $e->getMessage());
        }

        return [];
    }
}
