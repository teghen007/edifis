# Backend SPEC 06 — Timetable/Calendar & VACUUM (Phase 4/6)

White-paper §7.1, §7.2; ADR-013/014/015. Contract: `/timetable`, `/vacuum/query`, `/vacuum/command`, `audit-entry`.

---

## 1. Timetable & Calendar (T-6.1) — §7.2

### Models
```
timetable_entry{ id, class_id, subject_id, teacher_id, day_of_week, period_start, period_end, room?, approved_by?, approved_at? }
calendar_event{ id, title, type(term|exam|holiday|pta|sports|feast|other), starts_at, ends_at, scope(school|class?), created_by }
```
### Rules
- Authoring: `vice_principal` (or a timetable officer permission) creates/edits `timetable_entry`; entries are **pending** until `principal` approves (`approved_by/at`).
- `calendar_event` maintained by principal/VP/secretary; school-wide by default.
- Read is **role-scoped**: a subject_teacher sees their own periods; a class_master sees their class; a parent sees their child's; principal/VP see all.
- Served from the local node when offline; changes sync like any other record.
- **Must-test:** an unapproved timetable entry is not "live"; role-scoped reads return only the caller's slice; a teacher cannot author the timetable.

## 2. VACUUM — Principal command mode + AI co-pilot (T-6.2) — §7.1

> **Invariant:** unlimited academic reach, **never invisible**. Every command writes an immutable `audit_entry`. Finance is never directly editable. "Delete" = deactivate. Principal role only.

### 2a. AI co-pilot — read only
- `POST /vacuum/query {question}` → `{answer, records}`.
- Translate the natural-language question into a **safe, parameterised read** over the school's academic data (marks, attendance, promotion, students). Implementation options: a constrained query builder or an LLM that emits a whitelisted query spec — **never** free-form SQL, never a write. The assistant returns only data the Principal is already entitled to see; it must not fabricate records (answer is derived from `records`).
- **Must-test:** a query never mutates; an attempt to read finance returns the finance summary only via the same read rules (no ledger edits); injection attempts cannot escape the whitelisted read surface.

### 2b. Command authority — audited writes
```
VacuumCommand(command, target, payload, reason, confirm):
  require role = principal
  if target is a finance/ledger entity: throw forbidden        # finance not editable here
  if command in {bulk, destructive} and not confirm: throw validation_failed("confirm required")
  before = snapshot(target)
  apply:
     correct_mark        -> RecordMark(new revision, owner stays teacher-of-record) [SPEC 04]
     promote_student     -> set promotion outcome (override) [SPEC 04]
     repeat_student      -> set promotion outcome (override)
     override_promotion  -> OverridePromotion(decision, new_outcome, reason) [SPEC 04]
     deactivate_account  -> user.active=false + revocation append [SPEC 01]   # NOT delete
  after = snapshot(target)
  append audit_entry{ actor: principal, action: 'vacuum.'+command, before, after, reason, occurred_at: now }
  return { applied, audit }
```
- Reuse existing audited Actions (RecordMark, OverridePromotion, RevokeUser) — VACUUM is a **privileged entry point**, not a new write path that bypasses the rules.
- Capability toggle: a per-school flag enables/disables VACUUM; the flag change is itself audited.
- **Must-test:** (a) a finance target → `forbidden`; (b) a non-principal → `forbidden`; (c) a bulk command without `confirm` → `validation_failed`; (d) every successful command produces an `audit_entry` with correct before/after + reason; (e) `deactivate_account` retains the user's authored records (no deletion).

> **CARRY-OVER FIXES (REQUIRED — Phase 7) — the Phase-6 VACUUM audit is hollow and must be completed; this is the "power with a trail" guarantee (ADR-014):**
> 1. **Persist the `reason` in the vacuum `audit_entry`.** The Phase-6 `RunCommand` never writes `reason` to the audit, and drops it entirely for `correct_mark` (RecordMark gets no reason) and `deactivate_account` (revocations has no reason column). Thread `reason` through: add it to the audit entry, give `RecordMark` an optional reason, and add a `reason` column to revocations. Every VACUUM action's audit MUST carry the reason.
> 2. **Real before/after snapshots.** `snapshot()` currently returns `{target, at}` — capture the actual entity state (mark score, decision outcome, user.active) before and after, so the audit shows the true old→new.
> 3. **Fix `requireConfirm` classification.** It checks for command names `'bulk'`/`'destructive'` that never match the real commands, so `deactivate_account` runs without confirm. Classify the destructive/bulk commands explicitly (at minimum `deactivate_account`, and any multi-target command) and require `confirm` for them.
> 4. **Correct error code.** Non-principal and finance targets must return contract code `forbidden`, not `node_mode_unsupported` — throw a `ForbiddenException`, not `NodeModeUnsupportedException`.
> - **Must-test (additions):** a `correct_mark` and a `deactivate_account` audit entry both contain the supplied `reason`; before/after reflect the real changed values; `deactivate_account` without `confirm` → `validation_failed`; non-principal/finance → error `code: forbidden`.

## Outputs
```
app/Domain/Timetable/{Actions/{UpsertTimetableEntry,ApproveTimetable,UpsertCalendarEvent}.php, Models/{TimetableEntry,CalendarEvent}.php, Policies/*}
app/Domain/Vacuum/{Actions/{RunQuery,RunCommand}.php, Services/{QueryPlanner,VacuumGuard}.php}
app/Http/Controllers/Api/{TimetableController.php, VacuumController.php}
tests/Feature/{TimetableTest, VacuumQueryTest, VacuumCommandAuditTest}.php
```
