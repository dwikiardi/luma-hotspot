# Root Cause: Cross-MAC Device Identification Failure

## Problem
iOS/Android private MAC randomization causes MAC addresses to change periodically (~2 weeks for iOS 18+). When a returning guest's MAC changes, the current session lookup fails, forcing them to re-authenticate (CNA → room number → PAP).

## Root Cause Chain
1. **MAC-based session lookup fails** → MAC baru tidak dikenal
2. **Cookie-based lookup fails** → CNA WebView isolated, cookies tidak persist antar sesi
3. **JS fingerprint not yet available** → FingerprintJS hanya jalan setelah CNA WebView load (terlambat — CNA loop sudah terjadi)
4. **No passive network-layer identification** → Tidak ada mekanisme identificasi device SEBELUM CNA muncul

Result: Guest yang return dengan MAC baru harus login ulang. Grace period tidak membantu karena grace period hanya memperpanjang session di DB — tidak mengidentifikasi device.

## Solution: Device DNA (Phase B)

### Architecture
```
DHCP Discover (MAC baru)
  → MikroTik DHCP server → lease-script → POST /api/dhcp-hook
  → DeviceDnaService.recordFingerprint()
     → Store DHCP fingerprint (hostname, vendor_class_id, PRL, client_id)
     → Compute SHA256 hash
     → Find or create DeviceDna profile
     → If found (partial match by hostname/VCI/OUI): merge MAC into known_macs

Portal CNA opens (MAC baru)
  → Tier 0: DeviceDnaService.resolveIdentity()
     → Lookup latest DHCP fingerprint for this MAC
     → Find DNA profile by fingerprint_hash / MAC / OUI / hostname
     → If profile found with last_user_id → find active session → auto-login
  → Tier 1-3: existing MAC/cookie/fingerprint fallback
```

### Key Design Decisions
- **DHCP fingerprinting** dipilih karena passive (no user interaction), available SEBELUM CNA
- **Hash-based matching** dengan partial fallback (hostname pattern, VCI, OUI) menangani perubahan kecil
- **Known MACs aggregation** di DeviceDna memungkinkan cross-MAC identification
- **Confidence scoring** mencegah false positive — makin banyak match makin tinggi skor
- **Scheduled polling** dari MikroTik DHCP lease table menangkap hostname + client-id untuk device yang tidak trigger lease-script

### DHCP Fingerprint Signals
| Signal | DHCP Option | Bobot | Notes |
|--------|------------|-------|-------|
| Hostname (base) | 12 | Medium | Di-normalize (strip trailing hex) |
| Vendor Class ID | 60 | High | OS-specific ("MSFT 5.0", "android-dhcp-15") |
| Parameter Request List | 55 | High | Urutan option request — unique per OS |
| Client ID | 61 | Low | Sering = MAC, kadang persistent |
| MAC OUI | - | Medium | Identifikasi vendor/manufacturer |

### Future: Packet Capture (JA4D)
Full DHCP fingerprint (Option 55 PRL + Option 60 VCI) membutuhkan packet capture — MikroTik `/ip dhcp-server lease` hanya expose hostname dan client-id. Phase B2 dapat menambahkan:
- Docker container dengan `tcpdump` di bridge network MikroTik
- Atau DHCP relay/agent yang forward full DHCP packet
- Compute JA4D hash sesuai standar FoxIO

## Files Changed

### New Files
- `app/Models/DhcpFingerprint.php` — Model untuk captured DHCP fingerprint
- `app/Models/DeviceDna.php` — Model untuk agregasi device DNA profile
- `app/Services/DeviceDnaService.php` — Core matching service
- `database/migrations/2026_05_12_030000_create_dhcp_fingerprints_table.php`
- `database/migrations/2026_05_12_030001_create_device_dna_table.php`

### Modified Files
- `routes/api.php` — DHCP hook now calls DeviceDnaService::recordFingerprint
- `app/Http/Controllers/PortalController.php` — Added Tier 0 (Device DNA) to auto-login chain
- `app/Services/MikroTikApiService.php` — Added getDhcpLeases() for polling
- `routes/console.php` — Added DHCP lease poll scheduler (every 5 minutes)

## Rollback Plan
1. Remove new scheduled task from `routes/console.php` (DHCP lease poll)
2. Remove Tier 0 from `PortalController::show()` (lines 147-149)
3. Remove DHCP hook DNA call from `routes/api.php`
4. Drop tables: `device_dna`, `dhcp_fingerprints`
5. Delete new files: `DeviceDnaService.php`, models, migrations
