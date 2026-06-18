# Mobile SPEC 03 — Offline Sync (Phase 3)

Contract: `sync-envelope`, `/sync`, `/auth/revocations`. White-paper §5, §10; ADR-007/009.

## Drain + pull cycle
```
sync():
  if !connected: return
  drainOutbox():
     batch = outbox where synced=0, accountability types FIRST, up to maxBatch
     POST /sync {direction:push, since_cursor, items:batch}
     mark drained rows synced=1   # idempotency_replay also counts as success
  pullDeltas():
     POST /sync {direction:pull, since_cursor}
     apply items into Drift; store next_cursor
     for each conflict in response.conflicts:
        if mark owned by me: surface to teacher (banner/inbox), never silently drop
  pullRevocations(): force logout if my token/user listed
```
- Retry on `rate_limited` with exponential backoff + jitter. Never lose an outbox row on failure — bump `attempts`, keep it.
- Clock: trust server `synced_time` for ordering; the device clock is display-only.

## Must-test
- Full replay of an already-synced batch changes nothing locally or remotely.
- A surfaced marks conflict appears to the owning teacher and the rejected edit is visible (not dropped).
- Backoff schedule increases with jitter; an outbox row survives a failed sync and drains on the next success.

## Outputs
```
lib/features/offline_sync/{data/sync_service.dart, domain/conflict.dart, presentation/{sync_status.dart, conflict_inbox.dart}}
lib/core/db/{app_db.dart, outbox_dao.dart, sync_state_dao.dart}
test/sync_idempotency_test.dart
```
