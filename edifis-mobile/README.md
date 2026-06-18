# edifis-mobile — Unified Flutter App

One app, four roles (Admin/Bursar, Teacher, Student, Parent), **offline-first**. White-paper §7; ADR-009.

> **Builder:** read [`SPECS/00-overview.md`](SPECS/00-overview.md), then the feature spec for your task. Generate DTOs from `../edifis-contracts/schemas` — field-for-field. All writes go through the **outbox**, never straight to the API.

---

## Stack (ADR-009)

| Concern | Choice |
|---------|--------|
| Framework | Flutter 3.x / Dart 3 |
| State | Riverpod (`@riverpod` codegen) |
| HTTP | Dio (interceptors for auth + retry/backoff) |
| Local store | Drift (SQLite) — reads + the outbox |
| Serialization | `json_serializable` (DTOs mirror contract schemas) |
| QR | `mobile_scanner` |
| Signature | `signature` (capture → image ref) |
| Routing | `go_router`, role-gated |

## Offline-first model (the core idea)

```
UI action (issue / scan / mark)
  -> Repository.enqueue(event)        # write to Drift OUTBOX + local read store
  -> UI updates instantly from Drift  # no network needed
  ...later, when connected...
  -> SyncService.drainOutbox()        # push envelope to /sync, idempotent
  -> SyncService.pullDeltas()         # pull changes + conflicts + revocations
  -> reconcile into Drift
```
No screen calls the API directly for a write. Reads come from Drift; sync refreshes Drift. An event keeps the SAME UUID from creation through sync, so replays never double-post.

## Layout

```
lib/
  core/        # network (Dio), db (Drift), auth, config, errors, clock
  data/        # generated DTOs from edifis-contracts + mappers
  features/
    auth/  attendance/  issuance/  academics/  fees/  parent/  offline_sync/
    vacuum/  timetable/
      data/        # repositories (outbox-backed)
      domain/      # entities, use cases
      presentation/# screens, widgets, providers
  shared/      # common widgets, theming, role router
```

## Run

```bash
flutter pub get
dart run build_runner build --delete-conflicting-outputs   # codegen: riverpod, drift, json
flutter run \
  --dart-define=EDIFIS_LOCAL_BASE=https://pssnkwen.local/api \
  --dart-define=EDIFIS_CLOUD_BASE=https://pssnkwen.edifis.cm/api
# gates (must pass for Done):
dart format --set-exit-if-changed .
flutter analyze
flutter test
```

The app detects which base URL is reachable (local on-campus, cloud off-campus) per white-paper §4.

## Feature specs

| Spec | Feature | Phase |
|------|---------|-------|
| [`SPECS/00-overview.md`](SPECS/00-overview.md) | architecture, outbox, role router, DTO codegen | 0/1 |
| [`SPECS/01-auth-roles.md`](SPECS/01-auth-roles.md) | login, token cache, offline read, role routing | 1 |
| [`SPECS/02-issuance-attendance.md`](SPECS/02-issuance-attendance.md) | rubric checklist + signature, QR attendance | 2 |
| [`SPECS/03-offline-sync.md`](SPECS/03-offline-sync.md) | outbox drain, delta pull, conflict surfacing | 3 |
| [`SPECS/04-academics-parent.md`](SPECS/04-academics-parent.md) | marks entry, parent portal (no student views) | 4 |
| [`SPECS/05-principal-vacuum-timetable.md`](SPECS/05-principal-vacuum-timetable.md) | Principal VACUUM (AI co-pilot + audited commands), timetable/calendar | 4/6 |
