# Luma Network - WiFi Hotspot Management System

Hotspot management system using Laravel, Filament, FreeRADIUS, and MikroTik for captive portal authentication with device fingerprinting.

## Architecture

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   MikroTik   │────>│  FreeRADIUS  │────>│  PostgreSQL  │<────│   Laravel    │
│   Router     │<────│  (auth/acct) │<────│  (luma_db)   │────>│   Portal     │
└──────────────┘     └──────────────┘     └──────────────┘     └──────┬───────┘
                                                                        │
                                                                        │
                                                                 ┌──────┴───────┐
                                                                 │  FastAPI     │
                                                                 │  (Identity   │
                                                                 │   Engine)    │
                                                                 └──────────────┘
```

### Flow
1. User connects to WiFi → MikroTik redirects to captive portal
2. Portal collects device fingerprint → FastAPI scores trust (0-100)
3. User logs in (Room/Google/WhatsApp) → Laravel creates session
4. Laravel inserts user into `radcheck` table
5. MikroTik sends Access-Request → FreeRADIUS checks `radcheck` → Access-Accept
6. User gets internet access

## Quick Start

### 1. Clone & Configure

```bash
git clone https://github.com/dwikiardi/luma-hotspot.git
cd luma-hotspot
cp .env.example .env
# Edit .env with your settings
```

### 2. Start Services

```bash
docker compose up -d
docker exec luma_app php artisan key:generate
docker exec luma_app php artisan migrate
docker exec luma_app php artisan storage:link
```

### 3. Create Admin User

```bash
docker exec luma_app php artisan tinker --execute="
\App\Models\Admin::create([
    'name' => 'Admin',
    'email' => 'admin@luma.com',
    'password' => bcrypt('password')
]);
"
```

## Services

| Service | Container | Port |
|---------|-----------|------|
| Nginx | luma_nginx | 8081 |
| Laravel | luma_app | 8000 (internal) |
| PostgreSQL | luma_db | 5432 (internal) |
| FreeRADIUS | luma_radius | 1812/udp, 1813/udp |
| FastAPI | luma_fastapi | 8002 → 8001 (internal) |

## MikroTik Configuration

### RouterOS v7 (Recommended)

```routeros
# Set hotspot to use RADIUS
/ip hotspot profile set hsprof1 use-radius=yes radius-accounting=yes
/ip hotspot set hotspot1 profile=hsprof1

# Add RADIUS server
/radius add address=YOUR_SERVER_IP secret=luma_radius_secret service=hotspot timeout=3000

# Allow captive portal traffic
/ip hotspot walled-garden ip add dst-address=YOUR_SERVER_IP action=accept
/ip hotspot walled-garden ip add dst-port=80,443 protocol=tcp action=accept

# Set DNS
/ip dns set servers=8.8.8.8,8.8.4.4
```

### RouterOS v6

For v6, you need custom hotspot files. Download from:
```
http://YOUR_SERVER_IP:8081/mikrotik/hotspot-files?nas_id=YOUR_NAS_ID
```

Then upload `login.html` to MikroTik Files → hotspot folder.

### RADIUS Secret

The default shared secret is `luma_radius_secret`. Change it in:
- `/docker/radius/raddb/clients.conf` → `client mikrotik_luma`
- MikroTik → `/radius add ... secret=luma_radius_secret`

## Database Schema

### Key Tables

| Table | Purpose |
|-------|---------|
| `users` | User identities (room, Google, WhatsApp) |
| `radcheck` | RADIUS authentication (username, Cleartext-Password) |
| `radacct` | RADIUS accounting sessions |
| `radpostauth` | RADIUS post-authentication logs |
| `nas` | RADIUS client/NAS definitions |
| `routers` | MikroTik router configurations |
| `devices` | Device tracking (fingerprint hash) |
| `device_fingerprints` | Fingerprint data + trust scores |
| `user_sessions` | Login sessions with MAC, IP, timestamps |
| `portal_configs` | Per-tenant portal configuration |
| `tenants` | Multi-tenant organizations |

## Fingerprint & Trust Scoring

The FastAPI identity engine collects browser fingerprint data and calculates a trust score (0-100):

| Signal | Weight |
|--------|--------|
| Canvas hash | 15 |
| WebGL hash | 15 |
| Fonts hash | 10 |
| Audio hash | 10 |
| Screen resolution | 5 |
| Color depth | 2 |
| Hardware concurrency | 4 |
| Device memory | 4 |
| Timezone | 5 |
| Platform/OS | 5 |
| Browser | 3 |
| Languages | 2 |
| Touch support | 2 |
| Visitor ID (FingerprintJS) | 10 |

Risk deductions:
- Bot user-agent: -30
- Headless browser: -20

### API Endpoint

```bash
POST /api/fingerprint/analyze
Content-Type: application/json

{
  "user_agent": "...",
  "nas_id": "eden-canggu",
  "ip": "192.168.100.253",
  "canvas_hash": "...",
  "webgl_hash": "...",
  "screen_resolution": "1080x2400",
  ...
}
```

Response:
```json
{
  "fingerprint_hash": "abc123...",
  "trust_score": 85,
  "confidence": "high",
  "is_known_device": false,
  "risk_factors": []
}
```

## Testing

### Test FreeRADIUS Authentication

```bash
# From inside the server
docker exec luma_radius radtest 101 101 localhost 0 testing123

# From MikroTik private network
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

## Environment Variables

Key `.env` variables:

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_DATABASE=luma_hotspot
DB_USERNAME=postgres
DB_PASSWORD=secretpassword_staging

RADIUS_SECRET=luma_radius_secret
FASTAPI_URL=http://fastapi:8001

APP_URL=http://YOUR_SERVER_IP:8081
```

## License

Proprietary - All rights reserved.