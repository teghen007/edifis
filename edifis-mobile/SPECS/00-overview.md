# Mobile SPEC 00 — Overview, Outbox, Role Router, DTO Codegen

Read before any mobile task. ADR-009.

## DTOs come from contracts (do not invent shapes)

For every schema in `../edifis-contracts/schemas`, generate a Dart DTO under `lib/data/dto/` with `json_serializable`, matching fields/types/enums **exactly**. A test asserts each `schemas/*.example.json` round-trips. If a field isn't in the schema, it doesn't exist in the DTO.

## The outbox (the heart of offline-first)

Drift tables:
```
outbox(id TEXT pk, type TEXT, revision TEXT, payload TEXT json, created_at INT, synced INT default 0, attempts INT default 0)
local_<entity> tables for reads (students, marks, balances, sessions, ...)
sync_state(key TEXT pk, cursor TEXT, last_sync_at INT, revocation_pulled_at INT)
```
- A write use case: mint `id=uuidv7()`, build the contract payload, `INSERT INTO outbox` + update the local read table → UI reflects it immediately.
- `SyncService.drainOutbox()` posts a `SyncEnvelope{direction:push}` with unsynced rows (accountability types first); on success marks them `synced=1`. A replayed row is a server no-op (idempotency_replay) — safe.
- The same `id` is used at creation and on every retry, so nothing double-posts.

## Network base detection (white-paper §4)

`ConnectivityResolver` picks `EDIFIS_LOCAL_BASE` when the campus host resolves, else `EDIFIS_CLOUD_BASE`, else offline (serve from Drift). Dio interceptors attach the Sanctum token, handle `token_expired`/`token_revoked`, and retry `rate_limited` with exponential backoff + jitter.

## Role router (white-paper §7, ADR-013)

**Eight roles, NO student** (minors have no accounts). `go_router` redirect reads the authenticated role and routes to the role shell:
- `principal` → school dashboard + **VACUUM** (AI co-pilot + audited command mode)
- `vice_principal` → timetable/calendar manage + school-wide academics/attendance
- `bursar` → issuance/fees/students
- `class_master` → own class: attendance/marks/discipline
- `subject_teacher` → own subjects: marks/attendance
- `discipline_master` → discipline/exeats
- `secretary` → enrolment/records/printing
- `parent` → children dashboard (read-only)

A guard denies routes outside the role's matrix. Parents are read-only. The role enum must match the contract exactly.

## Quality gates
`dart format`, `flutter analyze`, `flutter test` all clean. The outbox replay path MUST have a test proving no double-post.
