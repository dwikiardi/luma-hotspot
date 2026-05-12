# Root Cause: Sync Duplikasi Session & Portal Session Lookup Mismatch

## Problem
- Sync creates 6 active sessions per user (one per DB router row) when multiple routers point to same physical MikroTik IP
- Portal cannot find sessions because it searches by `router_id` but sessions are registered under different `router_id`

## Architecture
```
Database routers:                  Physical MikroTik:
hotel-lantai1  ──┐
hotel-lantai2  ──┤
kafe-main      ──┤──→ 10.0.70.4 (1 device)
cowork-main    ──┤
...            ──┘
```
- `Router.nas_identifier` → `nas.shortname` → `nas.nasname` (= MikroTik IP)
- Multiple routers can (and do) share the same MikroTik IP
- `UserSession` has `router_id` FK → `routers.id`
- Partial unique index: `(user_id, router_id)` WHERE status IN ('active', 'disconnected')
- Every session lookup in portal & grace engine is scoped by `router_id`

## Fix Strategy (Opsi A): Group by IP + Cross-Router Portal

### Sync Deduplication
1. Resolve MikroTik IP for each router → group by IP
2. One `getActiveUsers()` call per IP group (using representative router)
3. Search session across ALL router IDs in the group
4. Found → reactivate. Not found → create for representative router

### Portal Cross-Router Lookup
Change all session lookups (MAC, cookie, fingerprint) in:
- `PortalController::show()` — 3 active session tiers
- `GracePeriodEngine::check()` — 3 grace session tiers

Change: `->where('router_id', $router->id)` → `->whereIn('router_id', $tenantRouterIds)`

Where `$tenantRouterIds = Router::where('tenant_id', $router->tenant_id)->pluck('id')`

## Files Affected
- `routes/console.php` — sync grouping
- `app/Http/Controllers/PortalController.php` — cross-router lookup
- `app/Services/GracePeriodEngine.php` — cross-router lookup

## Risk
- Cross-router hanya dalam 1 tenant (safe)
- Partial unique index prevents duplicate per router
- Grace period per-tenant tetap dihormati
