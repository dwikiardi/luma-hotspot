<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    public static function log(
        string $component,
        string $event,
        string $message,
        array $data = [],
        string $level = 'info',
        ?int $tenantId = null,
    ): void {
        try {
            ActivityLog::create([
                'tenant_id' => $tenantId ?? self::currentTenantId(),
                'level' => $level,
                'component' => $component,
                'event' => $event,
                'message' => $message,
                'data' => $data,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ActivityLogger failed', ['error' => $e->getMessage()]);
        }

        // Also write to file log for debugging
        $prefix = strtoupper($level);
        Log::info("[ACTIVITY][$component][$event] $message", $data);
    }

    public static function graceAutoLogin(string $room, int $sessionId, string $mac, string $ip): void
    {
        self::log('grace_engine', 'auto_login', "Room {$room} auto-login via grace period", [
            'session_id' => $sessionId,
            'mac' => $mac,
            'ip' => $ip,
        ], 'success');
    }

    public static function graceRequireLogin(string $reason): void
    {
        self::log('grace_engine', 'require_login', "Login required: {$reason}", [], 'warn');
    }

    public static function graceCheck(string $mac, bool $isCna, int $disconnectedCount, int $activeCount): void
    {
        self::log('grace_engine', 'check', "Grace check: MAC={$mac} CNA={$isCna} grace_sessions={$disconnectedCount} active={$activeCount}", [
            'mac' => $mac,
            'is_cna' => $isCna,
            'disconnected_sessions' => $disconnectedCount,
            'active_sessions' => $activeCount,
        ]);
    }

    public static function syncStarted(): void
    {
        self::log('mikrotik_sync', 'sync', 'MikroTik sync started');
    }

    public static function syncActiveUsers(int $count, array $users): void
    {
        self::log('mikrotik_sync', 'active_users', "MikroTik has {$count} active users", [
            'count' => $count,
            'users' => $users,
        ]);
    }

    public static function syncReactivate(string $user, int $sessionId, string $mac): void
    {
        self::log('mikrotik_sync', 'reactivate', "Reactivating session {$sessionId} for {$user} (MAC: {$mac})", [
            'user' => $user,
            'session_id' => $sessionId,
            'mac' => $mac,
        ], 'success');
    }

    public static function syncSessionCreated(string $user, int $sessionId, string $mac): void
    {
        self::log('mikrotik_sync', 'session_created', "Created session {$sessionId} for {$user} (MAC: {$mac})", [
            'user' => $user,
            'session_id' => $sessionId,
            'mac' => $mac,
        ]);
    }

    public static function syncMacUpdated(string $user, int $sessionId, string $oldMac, string $newMac): void
    {
        self::log('mikrotik_sync', 'mac_updated', "Updated MAC for {$user}: {$oldMac} -> {$newMac}", [
            'user' => $user,
            'session_id' => $sessionId,
            'old_mac' => $oldMac,
            'new_mac' => $newMac,
        ], 'warn');
    }

    public static function disconnectDetected(string $user, string $mac, string $ip, string $cause): void
    {
        self::log('radius', 'disconnect', "User {$user} disconnected: {$cause}", [
            'user' => $user,
            'mac' => $mac,
            'ip' => $ip,
            'cause' => $cause,
        ], 'warn');
    }

    public static function disconnectSession(string $user, int $sessionId, ?string $expiresAt): void
    {
        self::log('radius', 'session_disconnected', "Session {$sessionId} ({$user}) marked disconnected, grace until {$expiresAt}", [
            'user' => $user,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
        ], 'warn');
    }

    public static function mikrotikDisconnect(string $user, string $router): void
    {
        self::log('mikrotik_api', 'disconnect', "Disconnecting {$user} from MikroTik ({$router})", [
            'user' => $user,
            'router' => $router,
        ], 'warn');
    }

    public static function mikrotikLogin(string $user, string $redirectUrl): void
    {
        self::log('mikrotik_api', 'login', "Redirecting {$user} to MikroTik PAP login", [
            'user' => $user,
            'redirect_url' => $redirectUrl,
        ]);
    }

    public static function mikrotikConfigApplied(string $router): void
    {
        self::log('mikrotik_api', 'config', "Hotspot config applied to {$router}", ['router' => $router], 'success');
    }

    public static function portalOpened(string $mac, string $ip, bool $isCna): void
    {
        self::log('portal', 'opened', "Portal opened: MAC={$mac} IP={$ip} CNA={$isCna}", [
            'mac' => $mac,
            'ip' => $ip,
            'is_cna' => $isCna,
        ]);
    }

    public static function portalActiveRedirect(string $user, string $mac): void
    {
        self::log('portal', 'active_redirect', "Active session found for {$user} ({$mac}), redirecting to MikroTik", [
            'user' => $user,
            'mac' => $mac,
        ], 'success');
    }

    public static function portalSilentLogin(string $user, string $mac, string $ip): void
    {
        self::log('portal', 'silent_login', "Silent auto-login: {$user} (MAC: {$mac}, IP: {$ip})", [
            'user' => $user,
            'mac' => $mac,
            'ip' => $ip,
        ], 'success');
    }

    public static function portalLoginForm(string $reason): void
    {
        self::log('portal', 'login_form', "Showing login form: {$reason}");
    }

    public static function sessionCreated(string $user, int $sessionId, string $method): void
    {
        self::log('session', 'created', "Session {$sessionId} created for {$user} via {$method}", [
            'user' => $user,
            'session_id' => $sessionId,
            'method' => $method,
        ], 'success');
    }

    public static function sessionReactivate(string $user, int $sessionId): void
    {
        self::log('session', 'reactivated', "Session {$sessionId} reactivated for {$user}", [
            'user' => $user,
            'session_id' => $sessionId,
        ], 'success');
    }

    public static function radacctStart(string $user, string $mac, string $ip): void
    {
        self::log('radius', 'accounting_start', "RADIUS accounting start: {$user} MAC={$mac} IP={$ip}", [
            'user' => $user,
            'mac' => $mac,
            'ip' => $ip,
        ]);
    }

    public static function radacctStop(string $user, string $mac, string $cause): void
    {
        self::log('radius', 'accounting_stop', "RADIUS accounting stop: {$user} MAC={$mac} cause={$cause}", [
            'user' => $user,
            'mac' => $mac,
            'cause' => $cause,
        ], 'warn');
    }

    public static function pythonError(string $message): void
    {
        self::log('python', 'error', "Python error: {$message}", [], 'error');
    }

    public static function pythonOk(string $action): void
    {
        self::log('python', 'ok', "Python: {$action} OK", [], 'success');
    }

    private static function currentTenantId(): ?int
    {
        try {
            return filament()?->getTenant()?->id;
        } catch (\Throwable) {
            return null;
        }
    }
}
