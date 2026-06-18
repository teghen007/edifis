# Backend SPEC 02 — Event Backbone: Ledger, Issuance, Attendance, Audit (Phase 2)

The spine of the system. White-paper §8.3, §9, §15; ADR-002/003/010; invariants in `/AGENT_GUIDE.md` §4. Contract schemas: `issue-event`, `attendance-event`, `ledger-entry`, `audit-entry`.

> **The cardinal rule:** these tables are INSERT-ONLY. No migration adds `updated_at` to an event table. No repository exposes `update`/`delete`. Corrections are new events.

---

## 1. AppendOnlyRepository + Audit (T-2.1)

- `AppendOnlyRepository::append(array $attrs): Model` inserts; `void(string $id, string $reason): Model` appends a *new* void event referencing the original (never mutates it).
- Every append also writes an `audit_entry` (actor, device, before=null/after=payload, occurred_at). Mark/financial edits record before/after.
- **Must-test:** reflection test that the repo has no `update`/`delete`; voiding creates 2 rows (original + void), original unchanged.

## 2. Issuance (T-2.2)

### Catalogue import
- `POST /issuance/catalogue:import` ingests an Excel rubric (item, cost, form/default-set). Validating import (reuse the migration pipeline, SPEC 05): reject duplicates/malformed, never import silently.
- Produces `catalogue_item` rows and a default set per form.

### Issue-by-exception + one signature, many events
```
IssueItemsToStudent(batch_id, student_id, items[], signature_ref):
  Idempotency.applyOnce(batch_id, revision):
    for each item in items:                     # items = the CHECKED rubric lines
        append issue_event {
          id: uuid7(), revision, student_id, catalogue_item_id,
          cost: catalogue.cost, issued_at: now, staff_id: actor,
          signature_ref,            # SAME ref shared across the batch
          batch_id, status: 'issued'
        }
        PostLedgerDebit(issue_event)            # see Ledger
    return events + posted ledger entries
```
- One signature image backs all items in the batch (white-paper §9.1 Step 3).
- A **return** is `append issue_event{status:'returned', reason}` + a crediting ledger entry — never an edit.
- **Must-test:** issuing N checked items writes N issue_events + N ledger debits; replay of the same `{batch_id,revision}` writes nothing extra (idempotent); a return credits the ledger without touching the original event.

## 3. Ledger (T-2.3)

- `PostLedgerDebit(event)` appends `ledger_entry{amount:+cost, source_event_id:event.id}`.
- Returns/payments append negative amounts.
- `BalanceQuery(student_id)` = `SUM(amount)`; never store it. Optionally cache the computed value in Redis keyed by student, invalidated on new entry — but the source of truth is the sum.
- **Must-test:** balance after issue = sum of debits; after return = reduced; a replayed sync of the same ledger entry id does not double-count.

## 4. Attendance (T-2.4)

### Session model
- `attendance_session{id, class_id, subject_id, period, opened_at, opened_by, closed_at?}`. The session — not the date — is the unit of record (white-paper §9.2).

### Scan + default-on override + reconciliation
```
RecordScan(session_id, student_id, source):
  Idempotency.applyOnce(event.id, revision):
    append attendance_event{ id, session_id, student_id, scanned_at: now,
        source: 'qr_scan'|'manual_override', status: 'present',
        void_reason: required-if-override }
SessionTally(session_id):
  scanned = count(present events)
  return { scanned, headcount_entered_by_teacher }   # system shows both; does not police
```
- **Default-on override:** a present-but-cardless student is added as `source='manual_override'` with a reason — audited, not silently absent (ADR-010, white-paper §9.2). This path is enabled by default.
- Over-count (scanned > headcount) is surfaced to the teacher as a proxy signal; the system records, it does not block.
- A correction is `void` with reason, never an edit.
- **Must-test:** (a) duplicate scan of same student in a session is idempotent; (b) override requires a reason or → `validation_failed`; (c) tally reflects voids; (d) per-term summary (e.g. 58/60) derives from events.

## Outputs
```
app/Support/{AppendOnlyRepository.php, HasUuidV7.php, Idempotency.php, ClockService.php}
app/Domain/Audit/{Models/AuditEntry.php, Services/AuditLogger.php}
app/Domain/Issuance/{Actions/{ImportCatalogue,IssueItemsToStudent,ReturnItem}.php, Models/*, Repositories/IssueEventRepository.php}
app/Domain/Ledger/{Actions/PostLedgerDebit.php, Queries/BalanceQuery.php, Models/LedgerEntry.php}
app/Domain/Attendance/{Actions/{OpenSession,RecordScan,CloseSession,VoidScan}.php, Models/*, Queries/SessionTally.php}
tests/Feature/{IssuanceTest, LedgerBalanceTest, AttendanceTest}.php
tests/Unit/{AppendOnlyRepositoryTest, IdempotencyTest}.php
```
