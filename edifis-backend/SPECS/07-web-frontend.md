# Backend SPEC 07 — Web Staff Workspace (Filament + Livewire)  (Phase 9)

ADR-016. Server-rendered staff UI **inside `edifis-backend`** (not a separate project). Served by BOTH node and cloud. Reuses the same `Domain/*` Actions as the API — **no business logic in Blade/Livewire/Filament classes; they orchestrate Actions and render.**

> **Offline caveat (ADR-016):** Livewire is connection-bound. On campus the browser hits `https://<school>.local` (the node — LAN stays up during an internet outage); off campus it hits the cloud. Web is therefore fine for connected staff work, but is NEVER the only client for attendance/issuance (those stay on the offline Flutter app, Phase 11).

## 1. Install & access (T-9.1)
- Filament v3 panel. Auth via the existing users + Sanctum/session; gate panel + resource access with Spatie permissions (the 8 roles, no student).
- The panel boots in both `EDIFIS_MODE=cloud|local` (a `ModeGate` check hides cloud-only items like the parent-admin on a node).
- Layout/branding: school name + motto (GOD–PEACE–KNOWLEDGE), bilingual-ready.

## 2. Resources — connected workflows (T-9.2)
Each Filament Resource is a thin shell over Domain Actions:

| Resource | Backing Action(s) | Notes |
|----------|-------------------|-------|
| Students / Enrolment | `EnrolStudent`, `CaptureConsent` | consent required for minors; photo upload |
| Issuance | `ImportCatalogue`, `IssueItemsToStudent`, `ReturnItem` | rubric checklist + **signature pad** as a Livewire component → one batch signature, N events |
| Fees / Ledger | `BalanceQuery` (read), receipts | derived balance; never an editable balance field |
| Marks | `RecordMark` | per-record ownership; publish gate; class/subject scoping |
| Promotion | `ComputePromotion`, `OverridePromotion` | principal override is audited |
| Documents | `RenderReportCard/Receipt/Register/Transcript` | DOMPDF; print/download |
| Timetable & Calendar | `UpsertTimetableEntry`, `ApproveTimetable`, `UpsertCalendarEvent` | VP authors, Principal approves |

- Append-only invariants hold: no Resource exposes edit/delete on ledger/issuance/attendance; corrections go through return/void Actions.

## 3. VACUUM page (T-9.3) — Principal only
- A custom Filament Page gated to `principal` + the per-school VACUUM flag.
- **Co-pilot tab:** a question box → `RunQuery` (`/vacuum/query` equivalent) → render `answer` + `records`. Read-only.
- **Command tab:** trigger `correct_mark` / `override_promotion` / `deactivate_account` via `RunCommand` with a **mandatory reason** field + a **confirm** modal for destructive commands; show the returned audit entry ("recorded in audit trail"). Finance controls are not rendered.

## 4. Role-scoped visibility (T-9.4)
- Policies/scopes so a `class_master`/`subject_teacher` sees only their own class/subjects; finance hidden from non-bursar; discipline to SDM/VP; **no student role** anywhere.

## Outputs
```
app/Filament/Resources/*            # one per resource above (artisan make:filament-resource)
app/Filament/Pages/Vacuum.php       # the VACUUM page
app/Livewire/SignaturePad.php + resources/views/livewire/signature-pad.blade.php
app/Providers/Filament/StaffPanelProvider.php
resources/views/...                 # any custom Blade
tests/Feature/Web/*                  # Filament/Livewire Pest tests per resource + VACUUM gate
routes/web.php                       # Filament auto-registers; add any custom routes
```
**Must-test:** role sees only permitted resources/actions; a Filament action calls the Domain Action (assert via spy/DB effect, not raw Eloquent in the component); VACUUM rejects non-principal; boots in both modes.
