<?php

namespace App\Services;

use App\Models\DeviceDna;
use App\Models\DhcpFingerprint;
use App\Models\Router;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\Log;

class DeviceDnaService
{
    public function recordFingerprint(
        string $mac,
        ?string $ip,
        ?string $hostname,
        ?string $vendorClassId,
        ?string $parameterRequestList,
        ?string $clientId,
        Router $router
    ): void {
        $hash = DhcpFingerprint::computeHash($hostname, $vendorClassId, $parameterRequestList, $clientId);

        $fingerprint = DhcpFingerprint::create([
            'mac_address' => $mac,
            'ip_address' => $ip,
            'hostname' => $hostname,
            'vendor_class_id' => $vendorClassId,
            'parameter_request_list' => $parameterRequestList,
            'client_id' => $clientId,
            'fingerprint_hash' => $hash,
            'dhcp_server' => $router->nas_identifier,
            'router_id' => $router->id,
            'detected_at' => now(),
        ]);

        $this->updateOrCreateDnaProfile($fingerprint);
    }

    public function updateOrCreateDnaProfile(DhcpFingerprint $fp): ?DeviceDna
    {
        $mac = strtoupper($fp->mac_address);
        $oui = substr($mac, 0, 8);
        $hostnameBase = $this->normalizeHostname($fp->hostname);

        $hasSignal = $hostnameBase || $fp->vendor_class_id || $fp->parameter_request_list;
        if (!$hasSignal) return null;

        $hash = $fp->fingerprint_hash;

        $existing = DeviceDna::where('fingerprint_hash', $hash)->first();

        if ($existing) {
            return $this->mergeIntoProfile($existing, $fp, $mac, $oui, $hostnameBase);
        }

        $partial = $this->findPartialMatch($fp, $mac, $oui, $hostnameBase);

        if ($partial) {
            return $this->mergeIntoProfile($partial, $fp, $mac, $oui, $hostnameBase);
        }

        $confidence = $this->computeInitialConfidence($fp, $oui);

        return DeviceDna::create([
            'fingerprint_hash' => $hash,
            'known_macs' => [$mac],
            'known_hostnames' => $hostnameBase ? [$hostnameBase] : [],
            'known_ouis' => $oui ? [$oui] : [],
            'known_vendor_classes' => $fp->vendor_class_id ? [$fp->vendor_class_id] : [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_user_id' => $this->resolveUserIdFromMac($mac),
            'confidence' => $confidence,
            'match_count' => 1,
        ]);
    }

    public function findPartialMatch(DhcpFingerprint $fp, string $mac, ?string $oui, ?string $hostnameBase): ?DeviceDna
    {
        if ($hostnameBase) {
            $byHostname = DeviceDna::whereJsonContains('known_hostnames', $hostnameBase)->first();
            if ($byHostname) return $byHostname;
        }

        if ($fp->vendor_class_id) {
            $byVci = DeviceDna::whereJsonContains('known_vendor_classes', $fp->vendor_class_id)->first();
            if ($byVci) return $byVci;
        }

        return null;
    }

    public function mergeIntoProfile(DeviceDna $profile, DhcpFingerprint $fp, string $mac, ?string $oui, ?string $hostnameBase): DeviceDna
    {
        $macs = $profile->known_macs ?? [];
        if (!in_array($mac, $macs)) {
            $macs[] = $mac;
        }

        $hostnames = $profile->known_hostnames ?? [];
        if ($hostnameBase && !in_array($hostnameBase, $hostnames)) {
            $hostnames[] = $hostnameBase;
        }

        $ouis = $profile->known_ouis ?? [];
        if ($oui && !in_array($oui, $ouis)) {
            $ouis[] = $oui;
        }

        $vcis = $profile->known_vendor_classes ?? [];
        if ($fp->vendor_class_id && !in_array($fp->vendor_class_id, $vcis)) {
            $vcis[] = $fp->vendor_class_id;
        }

        $confidence = $this->computeMergedConfidence($profile, $fp, $oui, $hostnameBase);

        $userId = $profile->last_user_id ?? $this->resolveUserIdFromMac($mac);

        $profile->update([
            'known_macs' => $macs,
            'known_hostnames' => $hostnames,
            'known_ouis' => $ouis,
            'known_vendor_classes' => $vcis,
            'last_seen_at' => now(),
            'last_user_id' => $userId,
            'confidence' => $confidence,
            'match_count' => $profile->match_count + 1,
        ]);

        return $profile->fresh();
    }

    public function resolveIdentity(?string $mac, ?string $fingerprintHash, ?string $hostname): ?User
    {
        if ($fingerprintHash) {
            $dna = DeviceDna::where('fingerprint_hash', $fingerprintHash)->first();
            if ($dna && $dna->last_user_id) {
                ActivityLogger::log('device_dna', 'identity_resolved',
                    "DNA match by hash={$fingerprintHash} → user={$dna->last_user_id}",
                    ['dna_id' => $dna->id, 'user_id' => $dna->last_user_id]
                );
                return User::find($dna->last_user_id);
            }
        }

        if ($mac && $mac !== 'unknown') {
            $mac = strtoupper($mac);
            $oui = substr($mac, 0, 8);

            $dna = DeviceDna::whereJsonContains('known_macs', $mac)->first();
            if ($dna && $dna->last_user_id) {
                ActivityLogger::log('device_dna', 'identity_resolved',
                    "DNA match by MAC={$mac} → user={$dna->last_user_id}",
                    ['dna_id' => $dna->id, 'user_id' => $dna->last_user_id]
                );
                return User::find($dna->last_user_id);
            }

            if ($oui) {
                $dna = DeviceDna::whereJsonContains('known_ouis', $oui)->first();
                if ($dna && $dna->last_user_id) {
                    ActivityLogger::log('device_dna', 'identity_resolved',
                        "DNA match by OUI={$oui} → user={$dna->last_user_id}",
                        ['dna_id' => $dna->id, 'user_id' => $dna->last_user_id]
                    );
                    return User::find($dna->last_user_id);
                }
            }
        }

        if ($hostname) {
            $base = $this->normalizeHostname($hostname);
            if ($base) {
                $dna = DeviceDna::whereJsonContains('known_hostnames', $base)->first();
                if ($dna && $dna->last_user_id) {
                    ActivityLogger::log('device_dna', 'identity_resolved',
                        "DNA match by hostname={$base} → user={$dna->last_user_id}",
                        ['dna_id' => $dna->id, 'user_id' => $dna->last_user_id]
                    );
                    return User::find($dna->last_user_id);
                }
            }
        }

        return null;
    }

    public function findActiveSessionForUser(User $user, Router $router): ?UserSession
    {
        $tenantRouterIds = \App\Models\Router::where('tenant_id', $router->tenant_id)
            ->pluck('id')->toArray();

        return UserSession::where('user_id', $user->id)
            ->whereIn('router_id', $tenantRouterIds)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function normalizeHostname(?string $hostname): ?string
    {
        if (!$hostname) return null;

        $normalized = strtolower(trim($hostname));
        if (empty($normalized)) return null;

        if (preg_match('/^[\s\-_.]*$/', $normalized)) return null;
        if (strlen($normalized) < 2) return null;

        $normalized = preg_replace('/[_-][a-f0-9]{4,}$/i', '', $normalized);
        $normalized = preg_replace('/[_-][a-f0-9]{8,}$/i', '', $normalized);

        if (preg_match('/^iphone[-,\d]*$/', $normalized)) return 'iphone';
        if (preg_match('/^ipad[-,\d]*$/', $normalized)) return 'ipad';
        if (preg_match('/^galaxy-[a-z0-9]+/', $normalized)) return $this->pregExtract('/^galaxy-[a-z0-9]+/', $normalized);
        if (preg_match('/^sm-[a-z0-9]+/', $normalized)) return $this->pregExtract('/^sm-[a-z0-9]+/', $normalized);
        if (preg_match('/^android-[a-f0-9]+$/', $normalized)) return 'android';
        if (preg_match('/^android_/', $normalized)) return 'android';
        if (preg_match('/^redmi_[a-z0-9]+/', $normalized)) return $this->pregExtract('/^redmi_[a-z0-9]+/', $normalized);
        if (preg_match('/^moto g/', $normalized)) return 'motorola';
        if (preg_match('/^laptop-[a-z0-9]+/', $normalized)) return 'laptop';
        if (preg_match('/^desktop-[a-z0-9]+/', $normalized)) return 'desktop';
        if (preg_match('/^macbook/', $normalized)) return 'macbook';
        if (preg_match('/^windows-/', $normalized)) return 'windows';
        if (preg_match('/^win-/', $normalized)) return 'windows';

        return $normalized;
    }

    private function pregExtract(string $pattern, string $subject): string
    {
        preg_match($pattern, $subject, $m);
        return $m[0];
    }

    public function computeInitialConfidence(DhcpFingerprint $fp, ?string $oui): float
    {
        $score = 0.0;

        if ($oui) {
            $knownOui = \Illuminate\Support\Facades\DB::table('device_mac_histories')
                ->where('mac_address', 'LIKE', $oui . '%')
                ->exists();
            if ($knownOui) $score += 0.15;
        }

        if ($fp->vendor_class_id) $score += 0.25;
        if ($fp->parameter_request_list) $score += 0.30;
        if ($fp->hostname && $this->normalizeHostname($fp->hostname)) $score += 0.15;
        if ($fp->client_id) $score += 0.10;

        return min(0.70, $score);
    }

    public function computeMergedConfidence(DeviceDna $profile, DhcpFingerprint $fp, ?string $oui, ?string $hostnameBase): float
    {
        $score = 0.0;
        $macCount = count($profile->known_macs ?? []);
        $matchCount = $profile->match_count ?? 0;

        if ($macCount >= 2) $score += 0.20;
        if ($matchCount >= 3) $score += 0.15;
        if ($matchCount >= 10) $score += 0.10;

        if ($oui && in_array($oui, $profile->known_ouis ?? [])) $score += 0.10;
        if ($fp->vendor_class_id && in_array($fp->vendor_class_id, $profile->known_vendor_classes ?? [])) $score += 0.15;
        if ($hostnameBase && in_array($hostnameBase, $profile->known_hostnames ?? [])) $score += 0.15;

        if ($fp->fingerprint_hash === $profile->fingerprint_hash) $score += 0.25;

        return min(1.0, $score);
    }

    private function resolveUserIdFromMac(string $mac): ?int
    {
        $mac = strtoupper($mac);

        $session = UserSession::where('mac_address', $mac)
            ->whereIn('status', ['active', 'disconnected'])
            ->orderByDesc('login_at')
            ->first();

        if ($session) return $session->user_id;

        $rad = \Illuminate\Support\Facades\DB::table('radacct')
            ->where('callingstationid', $mac)
            ->orderByDesc('radacctid')
            ->first();

        if ($rad) {
            $user = User::where('identity_value', $rad->username)->first();
            if ($user) return $user->id;
        }

        $macHistory = \App\Models\DeviceMacHistory::where('mac_address', $mac)
            ->where('is_active', true)
            ->first();

        if ($macHistory) {
            $device = $macHistory->device;
            if ($device && $device->user_id) return $device->user_id;
        }

        return null;
    }
}
