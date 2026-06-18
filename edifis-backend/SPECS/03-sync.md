# Backend SPEC 03 — Synchronization (Phase 3)

Bidirectional, idempotent, conflict-aware sync between node and cloud. White-paper §5.1, §10; ADR-007/008. Contract: `sync-envelope.schema.json`, `/sync`, `/auth/revocations`.

---

## 1. Envelope endpoints (T-3.1)

- `POST /sync` accepts a `SyncEnvelope`. `direction=push` applies client items; `direction=pull` returns deltas since `since_cursor` + `next_cursor`.
- Cursor is opaque (e.g. an ULID/hi-watermark per type). The client stores `next_cursor` for next time.
- Accountability-lane items (`issue_event`, `attendance_event`, `ledger_entry`, `audit_entry`) are processed before `normal` items.

> **CARRY-OVER FIX (REQUIRED) — `ApplyEnvelope::pull()` must be fully implemented.** The Phase-3 version is a stub returning empty deltas, so cloud→node propagation — including delivery of persisted `mark_conflicts` to the owning teacher — does not work. Implement the real delta build: for the requesting node, return every record per type with `synced_time > since_cursor` (and the node's pending `conflicts[]`), set `next_cursor` to the high-watermark, and respect the accountability lane. Without this, the offline-first guarantee and conflict surfacing are incomplete. **Must-test:** a record created after a cursor appears in the next pull; a persisted mark conflict is delivered to the owning teacher's pull; replaying a pull with the same cursor is stable.

## 2. Idempotent apply (T-3.2)

> **T-3.2.0 (BLOCKING, do this first) — harden `App\Support\Idempotency::applyOnce`.**
> The Phase-2 implementation is **check-then-act and non-transactional** — under concurrent replay (two syncs of the same batch) or a mid-batch crash it can double-post, because events are inserted before the `idempotency_log` row. Sync is exactly where this happens, so it must be fixed before any sync apply is written. Required pattern — **claim-first, inside one transaction:**
> ```
> applyOnce(id, revision, fn):
>   DB::transaction:
>     claimed = DB.table('idempotency_log').insertOrIgnore({entity_id:id, entity_revision:revision, applied_at: now})
>     if claimed == 0:                      # PK (entity_id,entity_revision) already present
>         return { status: 'replay', id, revision }   # success-equivalent, fn NOT run
>     result = fn()                         # runs ONCE, same tx as the claim
>     return result
> ```
> The composite PK already exists on `idempotency_log` — use it as the concurrency guard via `insertOrIgnore` returning the affected-row count, **before** running `fn`, and wrap both in `DB::transaction` so a crash rolls back the partial batch AND the claim together. Then update `IssueItemsToStudent` and `RecordScan` to rely on this (they currently call the old helper).
> **Must-test (concurrency):** two parallel `applyOnce` calls with the same `{id,revision}` run `fn` exactly once (assert one result + one `replay`, and exactly N rows, not 2N); a `fn` that throws leaves NO idempotency_log row and NO partial event rows (full rollback).

```
ApplyItem(item):
  Idempotency.applyOnce(item.id, item.revision):   # now atomic, claim-first
     switch item.type:
        issue_event / attendance_event / ledger_entry / audit_entry:
            append if not present(id);    # append-only, replay = no-op
        student / consent:
            applyDemographicsOrConsent(item)   # see conflict rules
        mark:
            resolveMark(item)                   # see conflict rules
   else: return idempotency_replay (success-equivalent, no re-apply)
```
- **Must-test:** replaying an entire envelope changes nothing; partial replay (some new, some seen) applies only the new.

## 3. Conflict resolution per type (T-3.3) — white-paper §5.1

| Type | Rule | Implementation |
|------|------|----------------|
| issue_event / attendance_event / ledger_entry / audit_entry | **append-only** | both sides keep all events; UUID dedupe; no conflict possible |
| mark | **per-record ownership; cloud-wins only on TRUE conflict** | compare `revision_parent` lineage; if linear → apply; if divergent from same parent → cloud-wins, append a `conflict{resolution:cloud_wins, rejected_revision}` to the pull response and surface to the owning teacher (never silent) |
| student/demographics | **LWW with clock discipline** | cloud restamps `synced_time`; latest authoritative time wins on low-stakes fields only |
| consent | **versioned append** | new version appended; never overwrite |

```
resolveMark(incoming):
  current = find(incoming.id)
  if current is null: apply(incoming) via RecordMark; return
  if current.revision == incoming.revision: return replay   # idempotency guard (applies to ALL paths, incl. divergent)
  if incoming.revision_parent == current.revision:          # linear edit
      apply(incoming) via RecordMark; return                # audited
  # divergence from a shared parent = TRUE conflict
  winner = cloudVersion(current, incoming)                  # cloud authoritative
  record conflict{ id, resolution:'cloud_wins', winning_revision, rejected_revision:incoming.revision }
  append audit_entry(action='mark.conflict', before:incoming, after:winner)
# CARRY-OVER FIX (REQUIRED): the divergent path must be idempotent too — a node retries a rejected
# edit, so guard the mark_conflicts insert + audit by (mark_id, rejected_revision) existence, OR wrap
# resolveMark in Idempotency::applyOnce(incoming.id, incoming.revision). A replayed rejected edit must
# NOT create duplicate conflict rows or duplicate mark.conflict audit entries.
```
- **Must-test:** linear offline edit is accepted (NOT overwritten just because cloud synced later); a true divergent conflict yields cloud-wins + a surfaced `conflict`, and the rejected edit is logged.

## 3b. Sync correctness — hardening (Phase 7, GO-LIVE GATING)

The Phase-5 `pull()` works but has correctness gaps that cause silent divergence under real intermittent connectivity. These MUST be fixed before any production go-live:

- **Authoritative delta watermark, not `created_at`.** Stamp `synced_time` (cloud-authoritative, set when the cloud *applies/persists* a record — append-only creates, LWW updates, mark applies) OR a monotonic server sequence/ULID. Build pull deltas off **that**, not `created_at`. Otherwise a record created offline earlier but synced later (old `created_at`) is missed by a node pulling since a newer cursor → silent data loss. This is the whole reason `synced_time` exists (§5.1 clock discipline).
- **Unify cursor semantics.** `push` currently stores `cursor = item.revision` while `pull` treats the cursor as a timestamp — inconsistent. Use one monotonic watermark (the `synced_time`/sequence) everywhere.
- **Target conflicts by owner.** `pullConflicts` must filter to the conflicts whose owning teacher belongs to the requesting node/user — never deliver (and mark pulled) another node's conflicts.
- **Reliable (at-least-once) conflict delivery.** Do not mark `pulled_at` when building the response. Deliver until the client **acks** receipt on its next sync (then mark pulled), so a dropped response doesn't lose a conflict.
- **Must-test:** a record whose `synced_time` is after a cursor is returned even if its `created_at` is older; a conflict is delivered only to its owning node and survives a dropped response (redelivered until acked).

> **PRODUCTION-BLOCKING FIX (T-7.7) — `synced_time` is not stamped on marks.** `push()` stamps `synced_time` on the payload, and the append-only / student / consent resolvers persist it (they `create($payload)`). But the **mark** path applies via `RecordMark(...)` with explicit params, which never writes `synced_time` — so synced marks keep `synced_time = null` and pull's `where('synced_time','>',cursor)` excludes them **forever**: marks never propagate cloud→node. Fix: thread `synced_time` into `RecordMark` (set it on create AND update), or have `resolveMark` set it on the applied row. Audit every resolver path persists `synced_time`. **Must-test:** a mark applied via sync has a non-null `synced_time` and appears in a subsequent pull on another node.
> *Minor (same task):* unify the cursor format — `push()` stores `getPreciseTimestamp(6)` (µs int) while `pull()` emits an ISO-Zulu datetime from `synced_time`; the fallback typeCursor can mismatch on a null-cursor pull after a push. Use one representation everywhere.

## 4. Throttling & resilience (T-3.4)

- Horizon/Redis queues sync jobs (no direct DB writes on burst).
- Per-IP rate limit → `rate_limited` (HTTP 429) with `retry_after_seconds`.
- Clients retry with exponential backoff + jitter (`SYNC_BACKOFF_BASE_SECONDS`).
- Revocation list pulled within the same sync cycle (offboarding).
- **Must-test:** over-limit requests get 429 + retry_after; jittered retry eventually applies; accountability items flush before normal under a mixed batch.

## Outputs
```
app/Domain/Sync/{Actions/{ApplyEnvelope,BuildDelta}.php, Services/{CursorService,ConflictResolver,SyncQueue}.php, Jobs/ApplySyncBatch.php}
app/Http/Controllers/Api/SyncController.php
tests/Feature/{SyncIdempotencyTest, MarksConflictTest, SyncThrottlingTest}.php
```
