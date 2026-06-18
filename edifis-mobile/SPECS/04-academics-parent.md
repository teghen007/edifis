# Mobile SPEC 04 — Academics & Parent Views (Phase 4)

Contract: `mark`, `/academics/marks`, `/fees/.../balance`. White-paper §7. Read-mostly roles.

## Teacher marks entry
- Enter sequence marks for own subjects/classes → outbox `mark` events with `revision`/`revision_parent` (lineage for sync).
- Only own-subject classes are visible.

## (No student views)
Students have no accounts (ADR-013). Results/attendance/balance reach families through the **parent** role below. A student's own data is never exposed via a student login because there is none.

## Parent portal
- Per child: balance, results (published), attendance summary (e.g. 58/60), downloadable receipts/report cards.
- Result-day read burst: views read from Drift cache, refresh on sync (don't hammer the API).

## Must-test
- Unpublished marks are hidden from student/parent; a parent sees only their children; a teacher cannot open another class; a mark edit creates a new revision and enqueues to the outbox.

## Outputs
```
lib/features/academics/{data/marks_repository.dart, presentation/{marks_entry_screen.dart, results_view.dart}}
lib/features/fees/{data/fees_repository.dart, presentation/balance_view.dart}
lib/features/parent/{data/parent_repository.dart, presentation/children_dashboard.dart}
test/{marks_publish_gate_test.dart, parent_scope_test.dart}
```
