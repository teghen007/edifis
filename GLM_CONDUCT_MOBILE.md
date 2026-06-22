# TASK (MOBILE dev) — Conduct: discipline entry + report-card display   [DO NOT DELETE]

**START:** `git pull --no-edit`. Work only in `edifis-mobile/`.
**END:** `flutter analyze` clean → `git commit && git push`. Do NOT build the APK (Claude ships it).

## Contract (backend built in parallel)
- `POST /conduct`  body `{ student_id, term_id, stream_id, conduct_grade, punctuality?, comment? }`
  (roles: discipline_master/principal/vice_principal). Returns the saved record.
- `GET /conduct?stream_id=&term_id=` → list of `{ student_id, name?, conduct_grade, comment }`.
- The report card (`GET /results/report-card`) will ALSO return `conduct_grade` and `conduct_comment`
  (top level, may be empty).

## Build

### 1. Show conduct on the report card
- `lib/core/services/results_api.dart` → `ReportCard`: add `final String conductGrade, conductComment;`
  parse `j['conduct_grade']??''`, `j['conduct_comment']??''` (keep all existing fields working).
- `lib/features/results/report_card_screen.dart` → if `conductGrade` is non-empty, show it in the
  summary card (e.g. a `_stat('Conduct', r.conductGrade)` plus the comment as small text). Null-safe.

### 2. Discipline-master entry screen
- NEW `lib/features/staff/conduct_screen.dart` (ConsumerStatefulWidget): pick **stream** (reuse
  `myAssignmentsProvider.masteredStreams` if present, else `streamsProvider`) + **term**
  (`termsProvider`), then a simple list of students for that stream where the user sets a
  **conduct grade** (dropdown: Excellent/Good/Fair/Poor) + optional comment, and a Save button that
  `POST /conduct` per student. Keep it simple; show success/'​error snackbars. Fail-soft on Dio errors.
  - To list students: reuse the mastersheet or a students endpoint already used elsewhere
    (`/results/mastersheet?stream_id=&term_id=` returns students by name — if you need ids, use the
    existing students list the app already calls; check `lib/features/staff/students_screen.dart`).
- Route: add `/conduct` in `lib/shared/routing/app_router.dart` (+ import).
- Entry point: in `staff_home_screen.dart` `_buildFab`, add a small FAB for
  `role == 'discipline_master' || role == 'principal'` → `context.push('/conduct')`
  (lucide `shieldAlert` or `gavel` icon).

## Constraints
- No new packages. Match existing style (Riverpod, `dioProvider`, GlassCard, AppColors, **lucide_icons_flutter**).
- App must still render if the backend isn't deployed yet (conduct fields empty → hidden).
- `flutter analyze` = 0 errors. Remember: package is **lucide_icons_flutter** (NOT lucide_icons).

## Acceptance
- Report card shows Conduct when present; discipline master can open `/conduct`, set grades, save.
- analyze clean. Commit + push, tell Claude → he builds + ships + end-to-end tests.
