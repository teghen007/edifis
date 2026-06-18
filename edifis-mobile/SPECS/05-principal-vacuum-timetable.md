# Mobile SPEC 05 — Principal VACUUM & Timetable/Calendar (Phase 4/6)

Contract: `/vacuum/query`, `/vacuum/command`, `/timetable`, `audit-entry`. White-paper §7.1, §7.2; ADR-014/015.

## VACUUM (principal only)
- A dedicated **VACUUM** shell, gated to `principal` and to the per-school capability flag.
- **AI co-pilot tab:** a chat box → `POST /vacuum/query`; render the `answer` plus the supporting `records` as tappable lists. Read-only; no local mutation.
- **Command tab / inline actions:** from a record the Principal can trigger `correct_mark`, `promote_student`, `repeat_student`, `override_promotion`, `deactivate_account` → `POST /vacuum/command` with a **mandatory reason field** and, for bulk/destructive actions, a **confirm dialog**.
- Show that the action was logged ("recorded in audit trail") — make the audit visible, reinforcing "power with a trail".
- Finance actions are not offered here (UI must not present a ledger-edit control); a finance target returns `forbidden`.
- **Must-test:** non-principal cannot reach the VACUUM route; a command without a reason is blocked client-side; a bulk command shows the confirm dialog; the UI surfaces the returned audit entry.

## Timetable & Calendar
- All roles: a read-only, role-scoped timetable + calendar view on the home shell (own periods / own class / own child / school-wide).
- VP/timetable officer: create/edit timetable entries (go to outbox → sync); Principal: approve.
- Works offline from Drift; refreshes on sync.
- **Must-test:** role-scoped read shows only the caller's slice; an unapproved entry renders as pending; authoring is hidden from non-authoring roles.

## Outputs
```
lib/features/vacuum/{data/vacuum_repository.dart, presentation/{vacuum_shell.dart, copilot_chat.dart, command_sheet.dart}}
lib/features/timetable/{data/timetable_repository.dart, presentation/{timetable_view.dart, calendar_view.dart, timetable_editor.dart}}
test/{vacuum_guard_test.dart, timetable_scope_test.dart}
```
