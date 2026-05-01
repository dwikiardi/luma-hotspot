# Luma Network - WiFi Hotspot Management System

Sistem manajemen hotspot WiFi dengan captive portal, autentikasi RADIUS, grace period auto-reconnect, device fingerprinting, dan multi-tenant admin dashboard.

## Arsitektur

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   MikroTik    │────>│  FreeRADIUS  │────>│  PostgreSQL  │<────│   Laravel    │
│   Router      │<────│  (auth/acct) │<────│  (luma_db)   │────>│   Portal     │
└──────────────┘     └──────────────┘     └──────────────┘     └──────┬───────┘
                                                                         │
                                                                  ┌──────┴───────┐
                                                                  │  FastAPI     │
                                                                  │  (Identity   │
                                                                  │   Engine)    │
                                                                  └──────────────┘
```

### Alur Kerja (Flow)

1. **User konek WiFi** → MikroTik redirect ke captive portal (`/portal?nas_id=eden-canggu&client_mac=...`)
2. **Portal cek cookie** → Jika session aktif ada, auto-redirect ke MikroTik login URL (bypass portal)
3. **Portal cek grace period** → Jika device pernah login dan session expired < grace period, auto-login silent
4. **Portal tampilkan form login** → Pilih metode: Nomor Kamar, Google, WhatsApp
5. **Login via kamar** → Laravel buat user di `users` + `radcheck` (Cleartext-Password)
6. **Laravel redirect ke MikroTik login URL** → `http://{hotspot_address}/login?username=X&password=X&dst=...`
7. **MikroTik kirim Access-Request** → FreeRADIUS cek `radcheck` → Access-Accept
8. **MikroTik buka firewall** → User dapet akses internet
9. **MikroTik kirim RADIUS Accounting** (Start/Interim-Update/Stop) → FreeRADIUS simpan ke `radacct` + POST ke Laravel
10. **Laravel sync MAC/IP** dari RADIUS accounting ke `user_sessions`

### Grace Period Auto-Reconnect

Ketika session user expired/terputus, sistem memberi masa grace (default 4 jam):
- **MAC match** → +4 poin
- **Cookie match** → +4 poin
- **Fingerprint match** → +3 poin
- **IP match** → +2 poin
- **Score >= 3** → Auto-login (redirect ke MikroTik login URL tanpa form)

### Masalah yang Diketahui & Solusinya

#### 1. MAC Address = "unknown"
**Masalah:** MikroTik tidak selalu mengirim `client_mac` di URL redirect, banyak device (terutama iOS CNA) yang tidak mendapatkan MAC address dari MikroTik.

**Solusi:** MAC dan IP address diperbarui oleh RADIUS accounting (via radacct), bukan dari portal URL. Pastikan modul `rest` FreeRADIUS diaktifkan untuk POST accounting data ke Laravel, ATAU gunakan cron job untuk sync dari radacct.

#### 2. IP Address = IP Proxy (157.85.220.70)
**Masalah:** `$request->ip()` mengembalikan IP nginx proxy, bukan IP client asli.

**Solusi:** Nginx sudah dikonfigurasi `real_ip_header X-Forwarded-For` dan Laravel `TrustProxies(at: '*')`. Pastikan MikroTik mengirim header `X-Forwarded-For` atau gunakan IP dari `Framed-IP-Address` di RADIUS accounting.

#### 3. iOS CNA harus login ulang padahal grace period masih aktif
**Masalah:** iOS Captive Network Assistant (CNA) browser sementara yang:
- Tidak menyimpan cookie antar sesi
- Mengirim MAC "unknown" karena tidak di-forward oleh MikroTik
- Reset fingerprint setiap kali CNA terbuka

**Solusi:** Grace period engine menggunakan MAC-based matching sebagai fallback. Pastikan radacct ter-sync ke user_sessions sehingga MAC address tersimpan benar.

---

## Instalasi

### 1. Clone & Configure

```bash
git clone https://github.com/dwikiardi/luma-hotspot.git
cd luma-hotspot
cp .env.example .env
# Edit .env dengan setting database, app URL, dll
```

### 2. Start Services

```bash
docker compose up -d
docker exec luma_app php artisan key:generate
docker exec luma_app php artisan migrate
docker exec luma_app php artisan storage:link
```

### 3. Buat Admin User

```bash
docker exec luma_app php artisan tinker --execute="
\App\Models\Admin::create([
    'name' => 'Admin',
    'email' => 'admin@gmail.com',
    'password' => bcrypt('admin123')
]);
"
```

### 4. Buat Tenant & Router

```bash
docker exec luma_app php artisan tinker --execute="
\$tenant = \App\Models\Tenant::create(['name' => 'Eden Canggu', 'slug' => 'eden']);
\$router = \App\Models\Router::create([
    'tenant_id' => \$tenant->id,
    'name' => 'Eden Canggu Router',
    'nas_identifier' => 'eden-canggu',
    'hotspot_address' => '192.168.100.1',
    'routeros_version' => 7,
]);
\App\Models\PortalConfig::create([
    'tenant_id' => \$tenant->id,
    'active_login_methods' => ['room' => true, 'google' => true, 'wa' => false],
    'branding' => ['name' => 'Eden Canggu', 'color' => '#6366f1'],
    'session_timeout' => 14400,
    'grace_period_seconds' => 14400,
]);
"
```

### 5. Tambah Room Users

```bash
docker exec luma_app php artisan tinker --execute="
\$router = \App\Models\Router::where('nas_identifier', 'eden-canggu')->first();
foreach (range(101, 110) as \$room) {
    \$user = \App\Models\User::create([
        'tenant_id' => \$router->tenant_id,
        'identity_type' => 'room',
        'identity_value' => (string) \$room,
        'name' => 'Room ' . \$room,
    ]);
    \Illuminate\Support\Facades\DB::table('radcheck')->insert([
        'username' => (string) \$room,
        'attribute' => 'Cleartext-Password',
        'op' => ':=',
        'value' => (string) \$room,
    ]);
    \Illuminate\Support\Facades\DB::table('radusergroup')->insert([
        'username' => (string) \$room,
        'groupname' => 'default',
        'priority' => 1,
    ]);
}
"
```

### 6. Tambah NAS di FreeRADIUS

NAS router otomatis dibaca dari tabel `nas` di database oleh FreeRADIUS (modul SQL). Tambahkan entry:

```bash
docker exec luma_app php artisan tinker --execute="
\DB::table('nas')->insert([
    'nasname' => '192.168.100.1',
    'shortname' => 'eden-canggu',
    'type' => 'other',
    'ports' => 0,
    'secret' => 'luma_radius_secret',
    'community' => '',
    'description' => 'Eden Canggu MikroTik',
]);
"
```

---

## Services

| Service | Container | Port | Keterangan |
|---------|-----------|------|------------|
| Nginx | luma_nginx | 8081 | Reverse proxy, forward X-Forwarded-For |
| Laravel | luma_app | 8000 (internal) | Portal + Filament admin/tenant |
| PostgreSQL | luma_db | 5432 (internal) | Database |
| FreeRADIUS | luma_radius | 1812/udp, 1813/udp | RADIUS auth + accounting |
| FastAPI | luma_fastapi | 8002 → 8001 (internal) | Fingerprint identity engine |
| Scheduler | luma_scheduler | - | Laravel schedule:work |

---

## Konfigurasi MikroTik

### RouterOS v7 (Recommended)

```routeros
# Set hotspot pakai RADIUS
/ip hotspot profile set hsprof1 use-radius=yes radius-accounting=yes
/ip hotspot set hotspot1 profile=hsprof1

# Tambah RADIUS server
/radius add address=YOUR_SERVER_IP secret=luma_radius_secret service=hotspot timeout=3000

# Allow captive portal traffic (walled garden)
/ip hotspot walled-garden ip add dst-address=YOUR_SERVER_IP action=accept
/ip hotspot walled-garden ip add dst-port=80,443 protocol=tcp action=accept

# Set DNS
/ip dns set servers=8.8.8.8,8.8.4.4

# Hostspot login URL parameters (otomatis dikirim MikroTik)
# link_login=$(link-login-only)=$(link-orig)
# Identity=$(identity)
# nas_id=$(identity)
# client_mac=$(mac)
```

### RouterOS v6

Untuk v6, perlu custom hotspot files:

```
http://YOUR_SERVER_IP:8081/mikrotik/hotspot-files?nas_id=YOUR_NAS_ID
```

Upload `login.html` ke MikroTik Files → hotspot folder.

### RADIUS Secret

Default shared secret: `luma_radius_secret`. Ubah di:
- `docker/radius/raddb/clients.conf` → `client mikrotik_luma`
- MikroTik → `/radius add ... secret=luma_radius_secret`
- `.env` → `RADIUS_SECRET=luma_radius_secret`

---

## Database Schema

### Tabel Utama

| Table | Kegunaan |
|-------|----------|
| `users` | Identitas user (room, Google, WhatsApp) |
| `radcheck` | RADIUS authentication (username, Cleartext-Password) |
| `radacct` | RADIUS accounting sessions (MAC, IP, traffic data) |
| `radpostauth` | RADIUS post-auth logs |
| `nas` | RADIUS client/NAS definitions |
| `routers` | Konfigurasi MikroTik router per tenant |
| `devices` | Device tracking (fingerprint hash) |
| `device_fingerprints` | Fingerprint data + trust scores |
| `user_sessions` | Login sessions dengan MAC, IP, timestamps, status |
| `portal_configs` | Konfigurasi portal per-tenant |
| `tenants` | Multi-tenant organizations |
| `tenant_users` | User backend tenant |

### User Session Status

| Status | Keterangan |
|--------|------------|
| `active` | User sedang online |
| `disconnected` | Session terputus, masih dalam grace period (bisa auto-reconnect) |
| `expired` | Session sudah lewat grace period (harus login ulang) |

### Alur Status Session

```
Login → active → (RADIUS Stop/disconnect) → disconnected → (grace period expired) → expired
                                    ↑                              |
                                    └── auto-reconnect ─────────────┘
```

---

## Filament Admin Panel

Akses: `http://YOUR_SERVER_IP:8081/admin`

### Resources

| Resource | Kegunaan |
|----------|----------|
| RADIUS Users | Daftar user + password radcheck + sesi aktif + group |
| NAS / Routers | Daftar router + konfigurasi NAS + user aktif |
| Accounting | Riwayat sesi RADIUS + filter + export CSV |
| Tenant | Manajemen tenant |
| Tenant Users | Manajemen user backend tenant |
| Admin Users | Manajemen admin |
| Activity Log | Log aktivitas |

### Widgets Dashboard

| Widget | Kegunaan |
|--------|----------|
| Platform Stats | Total tenant, router, user, sesi aktif |
| Realtime Visitor | Pengunjung online saat ini |
| Active Sessions | Tabel sesi aktif (MAC, IP, durasi) |
| Fingerprint Score | Distribusi trust score device |
| Grace Period Log | Log sesi yang masuk grace period |
| RADIUS Accounting | Statistik akuntansi RADIUS |
| RADIUS Auth | Statistik autentikasi RADIUS |

---

## Filament Tenant Panel

Akses: `http://YOUR_SERVER_IP:8081/dashboard/venue/{slug}` (contoh: `/dashboard/venue/Eden`)

### Resources

| Resource | Kegunaan |
|----------|----------|
| Pengunjung Aktif | Sesi WiFi aktif di router tenant |
| Pengguna WiFi | Daftar user WiFi di router tenant |
| Riwayat Sesi | History sesi dengan MAC, IP, durasi, traffic |
| Konfigurasi Portal | Pengaturan form login portal |
| Router & Access Point | Kelola router milik tenant |
| Tim & Staff | Kelola staff tenant |

### Widget Analytics

| Widget | Kegunaan |
|--------|----------|
| Stats Overview | Total pengunjung, online, berdasarkan metode |
| Active Sessions | Tabel sesi aktif |
| Login per Day | Bar chart login per hari |
| Login Method | Doughnut chart distribusi metode login |
| Peak Hours | Bar chart distribusi jam sibuk |

---

## Filament Namespace Convention (v3)

**PENTING:** Filament v3 mengharuskan Resource class ada di sub-namespace:

```
Namespace: App\Filament\Admin\Resources\RadAcctResource
File:      app/Filament/Admin/Resources/RadAcctResource/RadAcctResource.php
```

**JANGAN** taruh Resource class langsung di `Resources\` namespace:
```
❌ App\Filament\Admin\Resources\RadAcctResource  (di file Resources/RadAcctResource.php)
✅ App\Filament\Admin\Resources\RadAcctResource\RadAcctResource  (di file Resources/RadAcctResource/RadAcctResource.php)
```

Page classes menggunakan namespace yang sama dengan Resource:
```php
// Pages/ListRadAcct.php
namespace App\Filament\Admin\Resources\RadAcctResource\Pages;
use App\Filament\Admin\Resources\RadAcctResource\RadAcctResource; // FQN

class ListRadAcct extends ListRecords
{
    protected static string $resource = RadAcctResource::class;
}
```

### AdminPanelProvider

Gunakan explicit resource registration (JANGAN `discoverResources`):

```php
->resources([
    TenantResource::class,
    RadUserResource::class,
    NasResource::class,
    RadAcctResource::class,
    // ...
])
```

`discoverResources()` menyebabkan error "Cannot declare class because name is already in use".

---

## Perbaikan & Catatan Teknis

### MAC/IP dari RADIUS Accounting

FreeRADIUS menyimpan data akuntansi di `radacct` tabel PostgreSQL (termasuk `Calling-Station-Id` = MAC, `Framed-IP-Address` = IP client). Laravel `RadiusAccountingController` menerima POST dari FreeRADIUS dan mensync ke `user_sessions`.

**Masalah umum:**
- `Framed-IP-Address` dari MikroTik sering dikirim dengan CIDR `/32` suffix (contoh: `192.168.100.250/32`). Fungsi `normalizeFramedIp()` menghapus suffix ini.
- `radacct.framedipaddress` disimpan sebagai PostgreSQL `inet` type, yang menyimpan CIDR. Ketika dibaca oleh Laravel, perlu di-strip `/32`-nya.

### PortalController - Active Session Redirect

Ketika user sudah punya session aktif dan buka portal lagi:

```php
// JANGAN redirect langsung ke dst URL
// ❌ return redirect($dstUrl);

// Redirect via MikroTik login URL agar MikroTik buka firewall
// ✅ return redirect($loginUrl);
// loginUrl = http://{hotspot_address}/login?username=X&password=X&dst=...
```

Tanpa redirect via MikroTik, firewall MikroTik tidak terbuka dan user tidak bisa akses internet.

### Cookie Session

```php
cookie('luma_session', $token, $minutes, '/', null, false, false, false, 'Lax')
//               name     ↑      ↑      ↑  ↑     ↑      ↑        ↑     ↑
//               value  minutes  path  domain secure httpOnly sameSite raw
```

- `secure=false` karena portal berjalan di HTTP (bukan HTTPS)
- `httpOnly=false` agar JavaScript bisa baca cookie
- `sameSite=Lax` agar redirect dari MikroTik bisa bawa cookie

### Nginx Proxy & X-Forwarded-For

```nginx
set_real_ip_from 172.18.0.0/16;
real_ip_header X-Forwarded-For;
```

Laravel `TrustProxies(at: '*')` mempercayai semua proxy. Namun, `request->ip()` sering mengembalikan IP nginx (172.18.x.x) bukan IP client asli. Untuk mendapatkan IP client, gunakan `request->header('X-Forwarded-For')` atau data dari RADIUS `Framed-IP-Address`.

### Docker cp Nested Directory Bug

`docker cp` untuk sebuah direktori membuat salinan bersarang dari nama direktori di dalam tujuan:

```bash
# ❌ BUG: Ini membuat /target/RadAcctResource/RadAcctResource/... (nested duplicate)
docker cp app/Filament/Admin/Resources/RadAcctResource/ luma_app:/var/www/html/app/Filament/Admin/Resources/RadAcctResource/

# ✅ BETUL: Copy file individual
docker cp app/Filament/Admin/Resources/RadAcctResource/RadAcctResource.php luma_app:/var/www/html/app/Filament/Admin/Resources/RadAcctResource/RadAcctResource.php
```

Selalu verifikasi dengan `docker exec ls` setelah copy.

---

## Testing

### Test FreeRADIUS Authentication

```bash
# Dari dalam server
docker exec luma_radius radtest 101 101 localhost 0 testing123

# Dari jaringan MikroTik
radtest 101 101 YOUR_SERVER_IP 1812 luma_radius_secret
```

### Test Portal Login

```bash
curl -X POST http://YOUR_SERVER_IP:8081/auth/room \
  -H "Content-Type: application/json" \
  -H "X-Fingerprint: test-fp" \
  -d '{"room_number":"101","nas_id":"eden-canggu","client_mac":"AA:BB:CC:DD:EE:FF"}'
```

### Test Fingerprint Scoring

```bash
curl -X POST http://YOUR_SERVER_IP:8081/api/fingerprint/analyze \
  -H "Content-Type: application/json" \
  -d '{"user_agent":"Mozilla/5.0","nas_id":"test","ip":"127.0.0.1","canvas_hash":"abc"}'
```

### Impersonate Tenant

```
http://YOUR_SERVER_IP:8081/impersonate/{userId}
```

Contoh: `/impersonate/2` untuk login sebagai tenant Eden Canggu.

---

## Environment Variables

Key `.env` variables:

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=luma_hotspot
DB_USERNAME=postgres
DB_PASSWORD=secretpassword_staging

RADIUS_SECRET=luma_radius_secret
FASTAPI_URL=http://fastapi:8001

APP_URL=http://YOUR_SERVER_IP:8081

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://YOUR_SERVER_IP:8081/auth/google/callback
```

---

## Filament v3 API Quick Reference

| v2 (OLD) | v3 (CURRENT) |
|----------|--------------|
| `BulkDeleteAction` | `DeleteBulkAction` |
| `Filament\Pages\Actions\Action` | `Filament\Actions\Action` |
| `getActions()` | `getHeaderActions()` |
| `discoverResources()` | `->resources([...])` (explicit) |
| `@php $this->variable` in Blade | `$variable` from `getViewData()` |
| Resource in `Resources\XResource.php` | Resource in `Resources\XResource\XResource.php` |

---

## License

Proprietary - All rights reserved.