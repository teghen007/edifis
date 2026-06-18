# edifis-backend — Cloud Brain + Lite Local Node

One Laravel 11 codebase, two run modes (`EDIFIS_MODE=cloud|local`, ADR-004). Holds all domain logic, the append-only ledgers, sync, Filament admin, and document generation.

> **Builder:** read [`SPECS/00-overview.md`](SPECS/00-overview.md) first, then the per-module spec for the task you're on. Implement against `../edifis-contracts/`. Obey the invariants in `/AGENT_GUIDE.md` §4.

---

## Stack (ADR-005/006/012)

| Concern | Choice |
|---------|--------|
| Framework | Laravel 11, PHP 8.3 |
| Runtime | Octane (Swoole/RoadRunner) in Docker |
| Admin UI | Filament PHP |
| AuthZ | Spatie Laravel-Permission (4 roles) |
| AuthN | Laravel Sanctum (short-TTL tokens + revocation list) |
| Multi-tenancy | stancl/tenancy v3 (cloud only; stripped on node) |
| Queue/sync | Horizon + Redis |
| DB | PostgreSQL 16 |
| Docs | DOMPDF (PDF), Laravel Excel (import/export) |
| Base | UnifiedTransform fork (scaffolding) |
| Tests | Pest; PHPStan ≥6; Pint |

## Layout

```
app/
  Domain/                 # ALL business logic lives here, one folder per module
    Tenancy/  Auth/  Sync/  Ledger/  Issuance/  Attendance/
    Academics/ Promotion/ Audit/ Documents/ Monitoring/ Students/ Consent/
    Timetable/ Vacuum/
  Http/Controllers/Api/   # thin controllers: validate -> Action -> Resource
  Filament/               # admin panels
database/{migrations,seeders,factories}
routes/                   # api.php, web.php, channels.php
tests/{Feature,Unit}
SPECS/                    # the build specs (read these)
```

Each `Domain/<Module>/` is structured: `Actions/` (one invokable use case each), `Models/`, `Data/` (DTOs), `Repositories/`, and module-local services. Controllers never hold logic.

## Run modes

`EDIFIS_MODE=cloud` → multi-tenant, parent portal, payment surface (deferred), monitoring aggregation.
`EDIFIS_MODE=local` → single school, tenancy + parent portal stripped, syncs up to cloud.

A `ModeGate` service answers "is feature X available in this mode?"; domain code asks the gate, never hard-codes the mode. Cloud-only endpoints return `node_mode_unsupported` on a node.

## Local dev

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan octane:start        # or: php artisan serve
# quality gates (must pass for any task to be Done):
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

Or via infra: `docker compose -f ../edifis-infra/cloud/docker-compose.yml up`.

## Module specs

| Spec | Module | Phase |
|------|--------|-------|
| [`SPECS/00-overview.md`](SPECS/00-overview.md) | architecture & conventions | 0 |
| [`SPECS/01-tenancy-auth.md`](SPECS/01-tenancy-auth.md) | Tenancy, Auth, Students, Consent | 1 |
| [`SPECS/02-event-backbone.md`](SPECS/02-event-backbone.md) | Ledger, Issuance, Attendance, Audit | 2 |
| [`SPECS/03-sync.md`](SPECS/03-sync.md) | Synchronization | 3 |
| [`SPECS/04-academics-promotion-documents.md`](SPECS/04-academics-promotion-documents.md) | Academics, Promotion, Documents | 4 |
| [`SPECS/05-compliance-ops.md`](SPECS/05-compliance-ops.md) | Provisioning, migration, backup, monitoring | 5 |
| [`SPECS/06-timetable-vacuum.md`](SPECS/06-timetable-vacuum.md) | Timetable/Calendar + VACUUM (Principal command mode & AI co-pilot) | 4/6 |
| [`SPECS/07-web-frontend.md`](SPECS/07-web-frontend.md) | Web staff workspace (Filament + Livewire), served node + cloud | 9 |
| [`SPECS/08-parent-portal-notifications.md`](SPECS/08-parent-portal-notifications.md) | Parent portal (cloud-direct PWA) + push notifications | 10 |
