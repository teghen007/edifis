# edifis-contracts — Single Source of Truth for the Wire Format

Both the backend and the mobile app implement against the artifacts here. Nothing crosses the network in a shape that isn't defined in this folder. This is what stops two AIs building two apps that don't fit together.

> **Builder rule:** never add an endpoint or DTO in `edifis-backend`/`edifis-mobile` that doesn't first exist here. If you need one, add it here first (it counts as an architect-reviewable change — flag it in `PROGRESS.md`).

---

## Contents

| Path | What |
|------|------|
| [`openapi/edifis.openapi.yaml`](openapi/edifis.openapi.yaml) | The HTTP API: auth, sync, issuance, attendance, academics, fees, students/consent, monitoring. OpenAPI 3.1. |
| [`schemas/`](schemas/) | JSON Schema (draft 2020-12) for every event and core entity. The backend validates against these; the mobile app generates DTOs from them. |
| `schemas/_error.schema.json` | The shared error model — the only error shape any endpoint returns. |
| `schemas/sync-envelope.schema.json` | The sync delta envelope (cursor, batch, idempotency, priority lane). |

## The event schemas (white-paper §15)

| Schema | Append-only? | Notes |
|--------|--------------|-------|
| `attendance-event.schema.json` | yes | `status: present\|void`; `void_reason` carries override/correction reason |
| `issue-event.schema.json` | yes | one per item; `signature_ref` may be shared across a batch; `status: issued\|returned\|void` |
| `ledger-entry.schema.json` | yes (derived) | `amount`, `source_event_id`; **balance = SUM(amount), never stored** |
| `student.schema.json` | no (LWW demographics) | keyed by Master PEA ID |
| `consent.schema.json` | versioned (append) | who/relationship/date/scope; new consent never overwrites old |
| `mark.schema.json` | owned-edit | per-record teacher ownership; carries revision lineage |
| `audit-entry.schema.json` | yes | actor, device, timestamp, before/after |

## Conventions

- All IDs are **UUID** strings (v7 where ordering matters).
- All timestamps are **RFC 3339 UTC**; the field `synced_time` is the cloud-restamped authoritative time (clock discipline, ADR/§5.1).
- Every event carries `id` (UUID) + `revision` (idempotency key). A replayed `{id, revision}` MUST be a no-op server-side.
- Money amounts are **integer minor units (CFA, no decimals)** — never floats.
- Enums are closed; unknown enum values are a validation error.

## How each side consumes this

- **Backend:** request validation (FormRequests) and API Resources must match these schemas exactly. A contract test loads each schema and asserts the corresponding Resource/Request conforms.
- **Mobile:** generate `json_serializable` DTOs that mirror these schemas field-for-field. A test asserts a sample payload from `schemas/*.example.json` round-trips.

## Validating the contracts

```bash
# OpenAPI lint (any of):
npx @stoplight/spectral-cli lint openapi/edifis.openapi.yaml
# JSON Schema example validation (node or python); see schemas/_validate.md
```
