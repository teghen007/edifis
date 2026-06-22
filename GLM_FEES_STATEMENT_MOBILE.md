# TASK (MOBILE dev) — Parent fee statement screen   [DO NOT DELETE]

**START:** `git pull --no-edit`. Work only in `edifis-mobile/`.
**END:** `flutter analyze` clean → `git commit && git push`. Do NOT build the APK (Claude ships it).

## Contract (backend built by Claude in parallel — build against this)
`GET /parent/children/{studentId}/fees` returns:
```json
{
  "balance": 114174,
  "currency": "XAF",
  "items": [
    { "label": "Tuition", "amount": 75000, "type": "charge", "date": "2026-01-15" },
    { "label": "Payment received", "amount": -30000, "type": "payment", "date": "2026-02-01" }
  ]
}
```
- `amount` is positive for charges, negative for payments. `balance` = sum (positive = owed).

## Build
1. **Service** in `lib/core/services/parent_api.dart` (it already exists — add to it):
   a `childFeesProvider = FutureProvider.family<Map<String,dynamic>, String>((ref, studentId) async {...})`
   that GETs `/parent/children/$studentId/fees`. On error return `{'balance':0,'items':[]}` (never throw).

2. **Screen** NEW `lib/features/parent/fees_statement_screen.dart` (ConsumerWidget, takes `studentId` +
   `studentName`): header shows **Balance** (big, red if >0 / green if <=0, formatted like "114,174 XAF"),
   then a list of items — each row: label + date on the left, amount on the right
   (charges in `AppColors.danger`, payments in `AppColors.success` with a leading "−"). Use GlassCard rows.
   Empty list → "No fee activity yet." Null-safe throughout.

3. **Route** `/fees-statement` in `lib/shared/routing/app_router.dart` (pass studentId+studentName via
   `extra` map, same pattern as `/report-card`).
   **Also fix:** `app_router.dart` currently imports `conduct_screen.dart` **twice** (a duplicate line)
   — remove the duplicate import while you're in there.

4. **Entry point** on `lib/features/parent/parent_dashboard_screen.dart`: add a GlossyButton
   "Fee Statement" (lucide `receipt` or `wallet` icon) near the existing "View Report Card" button →
   `context.push('/fees-statement', extra: {'id': _selectedId, 'name': selected['name'] ?? ''})`.

## Constraints
- No new packages. Match existing style (Riverpod, dioProvider, GlassCard, GlossyButton, AppColors,
  **lucide_icons_flutter** NOT lucide_icons). Reuse the money-formatting helper already in
  parent_dashboard_screen.dart if handy.
- App must render fine if the endpoint 404s (backend may deploy a bit later).
- `flutter analyze` = 0 errors.

## Acceptance
- Parent can open a per-child **Fee Statement** showing balance + itemised charges/payments.
- Renders fine before backend is live. Commit + push, tell Claude → he builds + ships + tests.
