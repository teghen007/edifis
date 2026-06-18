# Mobile SPEC 02 — Issuance & Attendance UIs (Phase 2)

Contract: `issue-event`, `attendance-event`, `/issuance/issue`, `/attendance/*`. White-paper §9. All writes go to the outbox (ADR-009).

## Issuance (admin/bursar) — white-paper §9.1
- Select/scan a student (QR pulls them up). App pre-loads that form's **default rubric, every item checked**.
- Bursar **unchecks** what wasn't received. Running total updates live (e.g. "10 items — 45,000 CFA").
- Student signs once on the signature pad → one outbox `issue_event` per checked item, all sharing `batch_id` + `signature_ref`. Ledger debits derive server-side.
- UI matches the white-paper mockup (checklist + total + single signature + confirm).
- **Must-test:** N checked items enqueue N outbox events with one shared batch/signature; unchecking removes that item; replay/drain double-post is impossible (same ids).

## Attendance (teacher) — white-paper §9.2
- Open session (class + subject/period + datetime). Scan QR cards; **live count** + names; teacher enters own **headcount**.
- UI surfaces the reconciliation: scanned vs headcount; if scanned > headcount, show the proxy warning. System records, does not block.
- **Default-on override:** add a present-but-cardless student manually → outbox `attendance_event{source:manual_override, void_reason}`. Default path, audited.
- Close session; print/export register.
- **Must-test:** duplicate scan of one student is idempotent in the outbox; override requires a reason; tally reflects voids; over-count shows the warning.

## Outputs
```
lib/features/issuance/{data/issuance_repository.dart, presentation/{issue_screen.dart, rubric_checklist.dart, signature_pad.dart}}
lib/features/attendance/{data/attendance_repository.dart, presentation/{session_screen.dart, scanner_view.dart, tally_bar.dart}}
test/{issuance_outbox_test.dart, attendance_outbox_test.dart}
```
