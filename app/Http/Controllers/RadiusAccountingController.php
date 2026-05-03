<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\User;
use App\Models\UserSession;
use App\Services\GracePeriodEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RadiusAccountingController extends Controller
{
    public function __construct(
        private GracePeriodEngine $graceEngine
    ) {}

    public function handle(Request $request)
    {
        $statusType = $request->input('Acct-Status-Type');
        $username = $request->input('User-Name', '');
        $mac = $this->normalizeMac($request->input('Calling-Station-Id', ''));
        $nasId = $request->input('NAS-Identifier', '');
        $nasIp = $request->input('NAS-IP-Address', '');
        $sessionId = $request->input('Acct-Session-Id', '');
        $framedIp = $this->normalizeFramedIp($request->input('Framed-IP-Address', ''));
        $inputOctets = (int) $request->input('Acct-Input-Octets', 0);
        $outputOctets = (int) $request->input('Acct-Output-Octets', 0);

        Log::info('RADIUS Accounting', [
            'status' => $statusType,
            'username' => $username,
            'mac' => $mac,
            'nas_id' => $nasId,
            'session_id' => $sessionId,
            'framed_ip' => $framedIp,
        ]);

        switch ($statusType) {
            case 'Start':
                $this->handleStart($username, $mac, $nasId, $nasIp, $sessionId, $framedIp);
                break;

            case 'Interim-Update':
                $this->handleInterimUpdate($username, $mac, $nasId, $sessionId, $framedIp, $inputOctets, $outputOctets);
                break;

            case 'Stop':
                $this->handleStop($username, $mac, $nasId, $sessionId, $inputOctets, $outputOctets);
                break;
        }

        return response('OK', 200);
    }

    private function normalizeFramedIp(string $ip): string
    {
        if (empty($ip)) {
            return '';
        }
        $ip = preg_replace('/\/\d+$/', '', $ip);

        return trim($ip);
    }

    private function normalizeMac(string $mac): string
    {
        if (empty($mac)) {
            return '';
        }
        $mac = strtoupper(str_replace(['-', '.', ''], ':', $mac));
        if (! str_contains($mac, ':') && strlen($mac) === 12) {
            $mac = substr($mac, 0, 2).':'.substr($mac, 2, 2).':'.substr($mac, 4, 2).':'.substr($mac, 6, 2).':'.substr($mac, 8, 2).':'.substr($mac, 10, 2);
        }

        return $mac;
    }

    private function findSession(string $username, string $mac, string $nasId): ?UserSession
    {
        $router = Router::where('nas_identifier', $nasId)->first();
        if (! $router) {
            return null;
        }

        $user = User::where('identity_value', $username)->first();

        $query = UserSession::where('router_id', $router->id)
            ->where('status', 'active')
            ->orderByDesc('login_at');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $session = $query->first();

        if (! $session && $mac) {
            $session = UserSession::where('router_id', $router->id)
                ->where('status', 'active')
                ->where('mac_address', $mac)
                ->orderByDesc('login_at')
                ->first();
        }

        return $session;
    }

    private function handleStart(string $username, string $mac, string $nasId, string $nasIp, string $sessionId, string $framedIp): void
    {
        $router = Router::where('nas_identifier', $nasId)->first();
        if (! $router) {
            return;
        }

        $user = User::where('identity_value', $username)->first();
        if (! $user) {
            return;
        }

        $config = $router->tenant->portalConfig;
        $sessionTimeout = $config->session_timeout ?? 14400;

        $session = $this->findSession($username, $mac, $nasId);

        if ($session) {
            $session->update([
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($sessionTimeout),
                'mac_address' => $mac ?: $session->mac_address,
                'ip_address' => $framedIp ?: ($nasIp ?: $session->ip_address),
            ]);
        } else {
            // Cari session yang match dengan MAC + router (bukan user_id)
            $existingByMac = UserSession::where('mac_address', $mac)
                ->where('router_id', $router->id)
                ->where('status', 'active')
                ->first();

            if ($existingByMac) {
                $existingByMac->update([
                    'last_seen_at' => now(),
                    'expires_at' => now()->addSeconds($sessionTimeout),
                    'user_id' => $user->id,
                    'ip_address' => $framedIp ?: ($nasIp ?: $existingByMac->ip_address),
                ]);
                return;
            }

            $device = $user->devices()->first();
            if (! $device) {
                $device = \App\Models\Device::create([
                    'user_id' => $user->id,
                    'fingerprint_hash' => 'fp-'.substr(md5($username), 0, 16),
                ]);
            }

            $session = UserSession::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'router_id' => $router->id,
                'mac_address' => $mac ?: 'unknown',
                'fingerprint_hash' => 'fp-'.substr(md5($mac ?: $username), 0, 16),
                'cookie_token' => UserSession::generateCookieToken(),
                'ip_address' => $framedIp ?: ($nasIp ?: '0.0.0.0'),
                'login_at' => now(),
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($sessionTimeout),
                'status' => 'active',
                'nas_id' => $router->nas_identifier,
                'login_method' => 'room',
                'user_agent' => 'RADIUS',
                'meta' => ['radius_session_id' => $sessionId],
            ]);
        }

        Log::info('RADIUS Start processed', [
            'username' => $username,
            'mac' => $mac,
            'framed_ip' => $framedIp,
            'session_id' => $session->id,
            'new' => ! $session->wasRecentlyCreated,
        ]);
    }

    private function handleInterimUpdate(
        string $username,
        string $mac,
        string $nasId,
        string $sessionId,
        string $framedIp,
        int $inputOctets,
        int $outputOctets
    ): void {
        $router = Router::where('nas_identifier', $nasId)->first();
        if (! $router) {
            return;
        }

        $config = $router->tenant->portalConfig;
        $sessionTimeout = $config->session_timeout ?? 14400;

        $session = $this->findSession($username, $mac, $nasId);

        if ($session) {
            $updateData = [
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($sessionTimeout),
                'meta' => array_merge($session->meta ?? [], [
                    'input_octets' => $inputOctets,
                    'output_octets' => $outputOctets,
                ]),
            ];
            if ($mac) {
                $updateData['mac_address'] = $mac;
            }
            if ($framedIp) {
                $updateData['ip_address'] = $framedIp;
            }
            $session->update($updateData);
        }
    }

    private function handleStop(
        string $username,
        string $mac,
        string $nasId,
        string $sessionId,
        int $inputOctets,
        int $outputOctets
    ): void {
        $router = Router::where('nas_identifier', $nasId)->first();
        if (! $router) {
            return;
        }

        $user = User::where('identity_value', $username)->first();

        $query = UserSession::where('router_id', $router->id)
            ->where('status', 'active');

        // Prioritaskan MAC match untuk multi-device support
        if ($mac) {
            $query->where('mac_address', $mac);
        } elseif ($user) {
            $query->where('user_id', $user->id);
        }

        $session = $query->orderByDesc('login_at')->first();

        if ($session) {
            $session->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
                'last_seen_at' => now(),
                'mac_address' => $mac ?: $session->mac_address,
                'meta' => array_merge($session->meta ?? [], [
                    'input_octets' => $inputOctets,
                    'output_octets' => $outputOctets,
                ]),
            ]);

            $this->graceEngine->onDisconnect($session);
        }

        Log::info('RADIUS Stop processed', [
            'username' => $username,
            'mac' => $mac,
            'session_found' => $session !== null,
            'session_id' => $session?->id,
        ]);
    }
}