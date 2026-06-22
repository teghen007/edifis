# TASK (local GLM-5.2, Kilo on PC) — Report Card v2: show Mention + class stats   [DO NOT DELETE]

**START:** `git pull --no-edit`. Work only in `edifis-mobile/`.
**END:** `flutter analyze` clean → `git commit && git push`. Do NOT build the APK (Claude ships it).

## Goal
Surface the new report-card fields the backend is adding (in parallel). Build against this CONTRACT —
the backend `GET /results/report-card` will return these NEW fields:
- top level: `mention` (string, e.g. "Very Good"), `class_average` (number)
- each `subjects[]` row: `class_avg`, `class_high`, `class_low` (numbers)
(They already return `coefficient` and `weighted`.)

## Files
- `lib/core/services/results_api.dart` — extend `ReportCard` and `SubjectResult`.
- `lib/features/results/report_card_screen.dart` — display them.

## Steps
1. **Model** (`results_api.dart`):
   - `ReportCard`: add `final String mention;` and `final String classAverage;`
     parse `mention: j['mention']??''`, `classAverage: '${j['class_average']??''}'`.
   - `SubjectResult`: add `final String classAvg;` parse `'${j['class_avg']??''}'`
     (class_high/low optional — add only if you use them).
   - Keep existing fields/positional args working (update all constructor call sites).

2. **UI** (`report_card_screen.dart`):
   - In the summary GlassCard (where Average/Grade/Position show), add a **Mention** stat and a
     **Class avg** stat (reuse the existing `_stat(label, value)` helper).
   - In each subject row, under the subject name (next to "Coef · Total"), add `Class avg ${s.classAvg}`
     so a parent sees how their child compares to the class.
   - Be null-safe: if a field is empty, hide it gracefully (the backend may not have deployed yet —
     the app must still render with the old response shape).

## Constraints
- No new packages. Match existing style (Riverpod, GlassCard, AppColors).
- `flutter analyze` = 0 errors.
- The screen MUST still work if `mention`/`class_average`/`class_avg` are missing (empty string) —
  don't crash on the old response.

## Acceptance
- Report card shows Mention + Class average + per-subject class average when present.
- Renders fine with the current (pre-deploy) response too.
- Commit + push, tell Claude → he builds + ships the APK and end-to-end tests.
