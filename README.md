# Luma Network — WiFi Hotspot Management System

Sistem captive portal untuk **hotel, villa, co-living, dan venue** dengan autentikasi multi-metode, auto-reconnect grace period, device fingerprinting, dan multi-tenant dashboard.

> **Status:** Production-ready staging di `http://103.137.140.6:8081`

---

## Arsitektur

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   MikroTik    │────▸│  FreeRADIUS  │────▸│  PostgreSQL  │◂────│   Laravel    │
│   Router      │◂────│  (auth/acct) │◂────│  (luma_db)   │────▸│   Portal     │
└──────────────┘     └──────────────┘     └──────────────┘     └──────┬───────┘
       │                                                                │
       │ OpenVPN                                                        │
       ▼                                                                ▼
┌──────────────┐                                              ┌──────────────┐
│  Server B    │◂──────── SSH ────────────────────────────────│  Server A    │
│  (non-LXC)   │                                              │  (LXC)       │
│  OpenVPN     │                                              │  Luma App    │
│  Docker      │                                              │  Supervisor  │
└──────────────┘                                              └──────────────┘
```

### Alur Autentikasi (Hotspot Login Flow)

```
1. User konek WiFi → MikroTik redirect ke captive portal
      GET /portal?nas_id=eden-canggu&client_mac=XX:XX
      
2. Portal cek cookie → jika session aktif → auto-redirect ke MikroTik login URL
      └─ Tidak ada cookie → cek Grace Period
      
3. Grace Period → cek session disconnected dalam masa grace
      ├─ Fingerprint match (+5pt) → auto-login
      ├─ Cookie match (+5pt) → auto-login
      ├─ iPhone CNA (CaptiveNetworkSupport) → auto-login jika user_id unik
      └─ Tidak match → tampilkan login page

4. User login (Nomor Kamar / Google OAuth) → buat session
      POST /auth/room → create user + radcheck + session → redirect to MikroTik

5. MikroTik → Access-Request → FreeRADIUS → cek radcheck → Access-Accept

6. MikroTik → Accounting-Start → FreeRADIUS → simpan di radacct
      └─ Scheduler sync: radacct → user_sessions (MAC, IP)
```

### Alur Disconnect & Auto-Reconnect (Grace Period)

```
7. User disconnect WiFi
      MikroTik → Accounting-Stop → FreeRADIUS → radacct.acctstoptime
      
8. Scheduler (setiap 60 detik):
      a. Deteksi disconnect: radacct.acctstoptime → session status = "disconnected"
      b. Sync timeout: expired_at < now() → status = "expired"
      c. Sync MAC/IP: radacct → user_sessions
      d. Cleanup: hanya keep 1 disconnected per user
      
9. User reconnect (dalam grace period):
      a. Android/Laptop: JS generate fingerprint → auto-redirect ✅
      b. iPhone CNA: deteksi CaptiveNetworkSupport → auto-redirect (jika user_id unik) ✅
      c. Tanpa sinyal: muncul login page → user ketik nomor kamar
```

### Alur Disconnect MikroTik via API

```
10. User login baru dengan MAC berbeda
      AuthController → MikroTikApiService.disconnectUser()
      └─ Server B → Docker OpenVPN → Python routeros-api (port 8728)
         └─ /ip/hotspot/active/remove [find where user=XXX]
```

---

## Quick Start

### Prasyarat

- Docker & Docker Compose
- MikroTik RouterOS v6/v7 dengan hotspot aktif
- Domain / IP publik untuk captive portal

### 1. Clone & Setup

```bash
git clone https://github.com/dwikiardi/luma-hotspot.git
cd luma-hotspot
cp .env.example .env
# Edit .env — minimal:
#   DB_PASSWORD=secretpassword_staging
#   APP_URL=http://103.137.140.6:8081
```

### 2. Start Services

```bash
docker compose up -d
docker exec luma_app php artisan key:generate
docker exec luma_app php artisan migrate
docker exec luma_app php artisan storage:link
```

### 3. Buat Admin & Tenant

```bash
# Admin
docker exec luma_app php artisan tinker --execute="
\App\Models\Admin::create(['name'=>'Admin','email'=>'admin@gmail.com','password'=>bcrypt('admin123')]);
"

# Tenant + Router + Portal Config
docker exec luma_app php artisan tinker --execute="
\$t = \App\Models\Tenant::create(['name'=>'Eden Canggu','slug'=>'Eden']);
\App\Models\Router::create(['tenant_id'=>\$t->id,'name'=>'Eden Canggu','nas_identifier'=>'eden-canggu','hotspot_address'=>'192.168.100.1','routeros_version'=>'v6']);
\App\Models\PortalConfig::create(['tenant_id'=>\$t->id,'active_login_methods'=>['room'=>true,'google'=>true],'session_timeout'=>0,'grace_period_seconds'=>172800,'shared_users'=>3]);
"

# Room users (101-110)
docker exec luma_app php artisan tinker --execute="
\$t = \App\Models\Tenant::where('slug','Eden')->first();
\$r = \App\Models\Router::where('tenant_id',\$t->id)->first();
foreach(range(101,110) as \$rm) {
    \$u = \App\Models\User::create(['identity_value'=>(string)\$rm,'identity_type'=>'room','name'=>'Room '.\$rm]);
    \DB::table('radcheck')->insert(['username'=>(string)\$rm,'attribute'=>'Cleartext-Password','op'=>':=','value'=>(string)\$rm]);
}
"
```

### 4. Konfigurasi MikroTik

```routeros
# RADIUS Server
/radius add service=hotspot address=103.137.140.6 secret=luma_radius_secret authentication-port=1812 accounting-port=1813

# Hotspot Profile
/ip hotspot profile add name=luma-portal hotspot-address=192.168.100.1 login-by=http-pap,http-chap,cookie http-cookie-lifetime=1d use-radius=yes radius-accounting=yes radius-interim-update=5m

# Hotspot Server
/ip hotspot add name=hotspot1 interface=bridge-lan address-pool=dhcp_pool profile=luma-portal

# Walled Garden (allow portal server)
/ip hotspot walled-garden ip add dst-address=103.137.140.6 action=accept
/ip hotspot walled-garden ip add dst-host=captive.apple.com action=accept
/ip hotspot walled-garden ip add dst-host=connectivitycheck.gstatic.com action=accept

# Redirect ke portal
/ip hotspot profile set luma-portal login-by=http-pap,http-chap,cookie http-redirect=yes redirect-url=http://103.137.140.6:8081/portal?nas_id=eden-canggu
```

---

## Database Schema

### Tabel Utama

| Tabel | Kegunaan |
|-------|----------|
| `users` | Identitas user (room number, Google email, WhatsApp) |
| `radcheck` | RADIUS auth (Cleartext-Password) |
| `radreply` | RADIUS reply attributes (Session-Timeout, Idle-Timeout) |
| `radacct` | RADIUS accounting (MAC, IP, traffic, session time) |
| `radpostauth` | RADIUS auth logs |
| `nas` | FreeRADIUS client definitions (MikroTik IP, secret) |
| `routers` | Konfigurasi MikroTik per tenant |
| `devices` | Device tracking (fingerprint hash) |
| `device_fingerprints` | Fingerprint data + trust scores + browser details |
| `user_sessions` | Session login dengan MAC, IP, status, cookies |
| `portal_configs` | Konfigurasi portal per-tenant |
| `tenants` | Multi-tenant organizations |
| `tenant_users` | User login tenant dashboard |
| `admins` | User login admin dashboard |
| `analytics_events` | Event tracking (portal_opened, login_success, auto_reconnect) |
| `analytics_daily` | Aggregasi harian unique visitors/sessions |

### Session Status Lifecycle

```
Login → active → disconnect (grace) → expired
              ↑                              │
              └── auto-reconnect ────────────┘
```

| Status | Arti | Grace Period |
|--------|------|-------------|
| `active` | User sedang online | — |
| `disconnected` | Baru disconnect, masih bisa auto-reconnect | ✅ `expires_at` = disconnect_time + grace_period |
| `expired` | Grace period habis | ❌ Harus login ulang |

---

## Services

| Service | Container | Port | Keterangan |
|---------|-----------|------|------------|
| Nginx | `luma_nginx` | 8081 | Reverse proxy, X-Forwarded-For |
| Laravel | `luma_app` | 8000 (internal) | Portal + Filament + API |
| PostgreSQL | `luma_db` | 5432 (internal) | Database |
| FreeRADIUS | `luma_radius` | 1812-1813/udp | RADIUS auth + accounting |
| FastAPI | `luma_fastapi` | 8002→8001 (internal) | Fingerprint identity engine |
| Supervisor | dalam `luma_app` | — | Scheduler auto-start, php-fpm |

---

## Filament Admin Panel

Akses: `http://103.137.140.6:8081/admin` (admin@gmail.com / admin123)

### Resources

| Resource | Fungsi |
|----------|--------|
| RADIUS Users | User + password radcheck + sesi aktif + group |
| NAS / Routers | Router + konfigurasi NAS + user aktif + real-time status |
| Accounting | Riwayat sesi RADIUS + filter + export CSV |
| Tenant | Manajemen tenant |
| Tenant Users | Manajemen user backend tenant |
| Admin Users | Manajemen admin |
| Activity Log | Log aktivitas |

---

## Filament Tenant Panel

Akses: `http://103.137.140.6:8081/dashboard/venue/Eden` (admin@eden.com / admin123)

### Resources

| Resource | Fungsi |
|----------|--------|
| **Dashboard** | Stats real-time (Online Luma, Online MikroTik, Grace Period) + MikroTik hotspot widget + chart |
| Pengunjung Aktif | Sesi WiFi aktif + tombol Putuskan |
| Pengguna WiFi | Daftar user + password + online count |
| Riwayat Sesi | History semua sesi |
| Log Device | Fingerprint + trust score + browser/OS + login count |
| **Laporan** | Total user, repeat MAC, identity type, grace period stats |
| Konfigurasi Portal | Form login methods, branding, timeout, grace period |
| Router & AP | Konfigurasi MikroTik + status real-time |
| Tim & Staff | Kelola staff tenant |

### Widgets (refresh 10 detik)

| Widget | Data |
|--------|------|
| StatsOverviewWidget | Online Luma + Online MikroTik + Login 7hr + Grace Period |
| ActiveSessionsWidget | Tabel sesi aktif (MAC, IP, durasi) |
| MikroTikHotspotWidget | User hotspot real-time + tombol disconnect |
| PeakHourChartWidget | Bar chart jam sibuk |
| LoginMethodChartWidget | Doughnut distribusi metode login |
| GracePeriodStatsWidget | Bar chart login per hari |

---

## Grace Period — Auto-Reconnect Scoring

Sistem scoring untuk menentukan apakah user perlu login ulang:

| Sinyal | Poin | Keterangan |
|--------|------|------------|
| **Fingerprint match** | +5 | Device yang sama (fingerprint browser) |
| **Cookie match** | +5 | Session yang sama (luma_session cookie) |
| **MAC match** | +2 | MAC address yang sama (jarang, karena random MAC) |
| **IP match** | +2 | IP address yang sama |
| **IP + NAS match** | +1 | Bonus match |

| Kondisi | Threshold | Auto-login? |
|---------|-----------|-------------|
| Fingerprint / Cookie ada | **1** | ✅ Satu sinyal cukup |
| Tanpa fingerprint/cookie | **2** | Perlu minimal IP match |
| iPhone CNA (CaptiveNetworkSupport) | **0** (lewati scoring) | ✅ Auto-login jika user_id unik |

### iPhone CNA Handling

iPhone Captive Network Assistant (CNA) adalah browser terbatas yang:
- ❌ Tidak menjalankan JavaScript → fingerprint tidak ter-generate
- ❌ Tidak menyimpan cookie
- ❌ MAC address random

**Solusi:** Deteksi user agent `CaptiveNetworkSupport` → auto-login jika semua disconnected session milik `user_id` yang sama (group tamu 1 kamar). Kalau ada user_id berbeda → login page.

---

## Fingerprint & Device Identity

### Flow

```
Browser → portal.blade.php → generateFingerprint()
  → redirect URL dengan ?fingerprint=fp-br-xxx
  → GracePeriodEngine baca dari query parameter
  → Match dengan session.fingerprint_hash → auto-login
```

### Fingerprint Generation

```javascript
// Portal JS - vanilla
fingerprint = "fp-br-" + hash(
    userAgent + screen.width + screen.height + 
    screen.colorDepth + timezone + hardwareConcurrency
);
```

### Fingerprint Log

Setiap session creation → `logDeviceFingerprint()` → simpan ke `device_fingerprints`:
- `fingerprint_hash`, `user_agent`, `platform`, `os_name`, `browser_name`
- `trust_score`, `confidence`, `is_known_device`, `match_count`

---

## Konfigurasi PortalConfig

### Timeout (Format MikroTik Suffix)

| Field | Format | Contoh | Default |
|-------|--------|--------|---------|
| `grace_period_seconds` | `2h`, `1d`, `30m`, `7200` | `2d` = 172800 detik | `2h` |
| `session_timeout` | `2h`, `1d`, `0` | `0` = tanpa batas | `0` |
| `idle_timeout` | `30m`, `1h`, `0` | `0` = tanpa batas | `0` |
| `shared_users` | Number | `3` | `3` |

### Push saat Save

| Setting | Push ke | Mekanisme |
|---------|---------|-----------|
| `session_timeout` > 0 | FreeRADIUS `radreply` | `Session-Timeout` attribute |
| `idle_timeout` > 0 | FreeRADIUS `radreply` | `Idle-Timeout` attribute |
| `shared_users` | MikroTik API | `/ip/hotspot/user/profile/set` |
| `grace_period_seconds` | Laravel only | `GracePeriodEngine` |

---

## Auth0 / Google OAuth

### Google via Socialite (Production)
```env
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxx
GOOGLE_REDIRECT_URI=http://103.137.140.6:8081/auth/google/callback
```
Setup: https://console.cloud.google.com → OAuth consent screen → Web application

### Auth0 (Unified Login — butuh HTTPS)
```env
AUTH0_DOMAIN=dev-xxx.us.auth0.com
AUTH0_CLIENT_ID=xxx
AUTH0_CLIENT_SECRET=xxx
# Note: Auth0 perlu HTTPS untuk production
```

---

## Scheduler Tasks

Semua berjalan otomatis via Supervisor di `luma_app`:

| Task | Interval | Fungsi |
|------|----------|--------|
| Expired session check | 1 menit | `active` + `expires_at < now()` → `disconnected` |
| Disconnect detection | 1 menit | `radacct.acctstoptime` → `user_sessions` status `disconnected` |
| MAC/IP sync | 1 menit | Sync `radacct.callingstationid` + `framedipaddress` → `user_sessions` |
| Grace expire | 5 menit | `disconnected` + `expires_at < now()` → `expired` |
| Duplicate cleanup | 5 menit | Keep 1 `disconnected` per user, expire sisanya |
| Daily analytics | 00:05 | Aggregate daily visitor stats |

---

## FreeRADIUS Configuration

### Key Files
| File | Lokasi | Fungsi |
|------|--------|--------|
| `clients.conf` | `/etc/raddb/clients.conf` | MikroTik NAS client (secret: `luma_radius_secret`) |
| `mods-enabled/sql` | `/etc/raddb/mods-enabled/sql` | PostgreSQL connection, auth & acct queries |
| `mods-enabled/rest` | (disabled) | HTTP POST accounting ke Laravel (fallback to scheduler) |

### Auth Query (radcheck)
```sql
SELECT id, UserName, Attribute, Value, Op 
FROM radcheck 
WHERE Username = '%{SQL-User-Name}' 
ORDER BY id
```

### Accounting Query
```sql
INSERT INTO radacct (...) VALUES (...)
-- Start: insert
-- Interim-Update: update traffic
-- Stop: update acctstoptime
```

---

## MikroTik API Integration

### Koneksi
```
luma_app → SSH → Server B (103.137.141.8)
  → docker exec openvpn → Python routeros-api → MikroTik (10.0.70.4:8728)
```

### Command yang Didukung
| Command | Fungsi |
|---------|--------|
| `/ip/hotspot/active/remove` | Disconnect user dari hotspot |
| `/ip/hotspot/active/print` | List user aktif |
| `/ip/hotspot/user/profile/set` | Set shared-users |
| `/system/resource/getall` | Cek koneksi (reachability) |

### Setup Server B

```bash
# Di Server B (103.137.141.8)
# 1. Tambah support ke docker group
sudo usermod -aG docker support

# 2. Install Python routeros-api di container OpenVPN
docker exec openvpn pip install routeros-api --break-system-packages

# 3. Install SSH client & sshpass
docker exec openvpn apk add openssh-client sshpass

# 4. Tambah SSH key dari luma_app ke Server B
echo "ssh-ed25519 AAAAC3..." >> ~/.ssh/authorized_keys
```

---

## Troubleshooting

### MAC Address = "unknown"
- MikroTik tidak kirim `client_mac` → diisi `unknown`
- Fix: MAC & IP diupdate dari RADIUS accounting (`radacct.callstationid`, `radacct.framedipaddress`)
- Scheduler sync task setiap 1 menit

### IP Address = 157.85.220.70 (IP proxy)
- `$request->ip()` return IP nginx proxy
- Fix: Gunakan `$request->query('ip')` / `X-Forwarded-For` / `X-Real-IP`
- IP asli dari RADIUS `Framed-IP-Address` (strip `/32` suffix)

### iPhone CNA tidak auto-login
- Cek user agent mengandung `CaptiveNetworkSupport`
- Cek `portal_configs.grace_period_seconds` masih valid
- Cek `user_sessions` status `disconnected` + `expires_at > now()`
- Cek semua disconnected session punya `user_id` yg sama

### PostgreSQL column type inet error
- `ip_address` column tidak terima string kosong `""`
- Fix: `!empty($clientIp) ? $clientIp : null`

### Container Docker OpenVPN restart → SSH tools hilang
- Fix: install lagi `apk add openssh-client sshpass` atau buat custom Dockerfile

### Filament "portalApp is not defined"
- Alpine.js v3 tidak support async method di object literal
- Fix: ganti ke vanilla JS dengan fungsi global

---

## Development Notes

### Filament v3 Conventions
- Resource class di sub-namespace: `Resources\XResource\XResource.php`
- Gunakan `->resources([...])` explicit, bukan `discoverResources()`
- Widget refresh: `protected static ?string $pollingInterval = '10s'`
- File size: Blade views di `resources/views/filament/{panel}/`

### Database
- PostgreSQL dengan extension `inet` untuk IP address
- `radacct.framedipaddress` stored as `inet` dengan CIDR `/32` — perlu strip
- Session status enum: `active`, `disconnected`, `expired`

### SSH Key Management
- Container PHP-FPM runs as `www-data` → SSH key di `/var/www/.ssh/id_ed25519`
- Use `-i /var/www/.ssh/id_ed25519` explicit dalam command
- Use `-o UserKnownHostsFile=/dev/null` untuk non-interactive

---

## License

Proprietary — All rights reserved.