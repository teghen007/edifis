# Backend SPEC 00 — Overview & Conventions

Read this before any other backend spec. It defines the shapes everything else fills in.

## Request lifecycle (every endpoint follows this)

```
HTTP request
  -> Route (routes/api.php)
  -> FormRequest        # validation ONLY; rules mirror the contract schema
  -> Controller method  # thin: no logic; calls one Action
  -> Action (invokable) # the use case; pure, testable, in app/Domain/<M>/Actions
  -> Repository         # persistence; append-only repos expose append()/void() only
  -> API Resource       # response shape; MUST match edifis-contracts/openapi
```

If you write an `if` with business meaning inside a controller, move it to an Action.

## Module anatomy

```
app/Domain/<Module>/
  Actions/        # one invokable class per use case (IssueItemsToStudent, etc.)
  Models/         # Eloquent models (UUID PKs)
  Data/           # typed DTOs (spatie/laravel-data style or plain readonly classes)
  Repositories/   # data access; AppendOnlyRepository base for event tables
  Services/       # cross-cutting module services (e.g. ModeGate, ClockService)
```

## Non-negotiable base classes (build these in Phase 0/2 and reuse)

- `App\Support\HasUuidV7` — trait minting UUIDv7 PKs on create.
- `App\Support\AppendOnlyRepository` — abstract; provides `append(array): Model` and `void(string $id, string $reason): Model` (void = append a void event, NOT an update). Deliberately has **no** `update`/`delete`. A unit test asserts those methods are absent.
- `App\Domain\Tenancy\Services\ModeGate` — `cloud(): bool`, `local(): bool`, `requireFeature(string $feature): void` (throws → `node_mode_unsupported`).
- `App\Support\ClockService` — `now()` returns device time; `authoritativeStamp()` is set only by the cloud at sync (clock discipline, §5.1). Domain code never reads the raw system clock for cross-node ordering.
- `App\Support\Idempotency` — `applyOnce(string $id, string $revision, Closure $apply)`; a repeated `{id,revision}` returns the prior result and never re-applies.

## Money

Amounts are **integer CFA minor units**. Never float, never store a derived balance. Balance is always `SUM(ledger_entry.amount)` computed on read (cache the read in Redis if needed, never as a writable column).

## Errors

Throw typed domain exceptions that an exception handler maps to the contract error model (`edifis-contracts/schemas/_error.schema.json`). One shape out, always.

## Tenancy

On cloud, every domain query is tenant-scoped by stancl/tenancy. On node, tenancy is a no-op. Never call `tenant()` inside an Action — resolve context through a `TenantContext` service so the same Action runs in both modes.

## Testing baseline

- Pest. Feature tests hit routes with Sanctum auth; Unit tests cover Actions.
- Event/ledger/sync code MUST include: an **idempotency** test (replay = no double-apply) and an **append-only** test (no update/delete path).
- Factories for every model; seeders build one demo school (use the real classes/teachers from `RESOURCES/IMPORTANT--SMS-RESEARCH-DATA-PACKAGE.md` as seed data).
