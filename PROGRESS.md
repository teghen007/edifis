# PROGRESS — Living Ledger

## Top of mind

- **Phase 14 COMPLETE.** 102 tests, 370 assertions, 0 failures on PostgreSQL 16.
- **Backend complete.** Mobile: Flutter app analysed clean, 2 tests green, FCM ready.
- **✅ RESOLVED (T-14.5, architect-verified): onboarding approval is `pea_admin`-gated.** `role:pea_admin` middleware on list+approve (non-admin → 403), role seeded + restricted to central admins, audit actor_role from the real user. Onboarding is safe to go public. Earlier gap below kept for history.
- **(history) 🔴 Phase 14 SECURITY GAP (now fixed): no `pea_admin` gate on onboarding approval.** `OnboardingController::approve` (and `list`) require only `auth:sanctum` → ANY logged-in user can onboard arbitrary schools (privilege escalation). The audit entry hardcodes `actorRole:'pea_admin'` (false attribution). The `pea_admin` role likely isn't seeded. **Fix (T-14.5):** define + seed a `pea_admin` role; gate approve/list/schools on it (`role:pea_admin` middleware or `abort_unless($user->hasRole('pea_admin'),403)`); set the audit actor_role from the real user; add a test that a non-pea_admin gets 403.

## PostgreSQL 16: 25 migrations, 102 tests, 370 assertions, 0 failures

## Phase 14 — myedifis.com + Request→Approve Onboarding — DONE

- `GET /` — public landing page with school request form (Tailwind, client-side JS submit)
- `POST /api/onboarding/request` — public, stores school request with status `pending`. Duplicate pending requests return `already_submitted`.
- `GET /api/onboarding/requests` — PEA admin view (auth:sanctum)
- `POST /api/onboarding/requests/{id}/approve` — PEA admin action:
  - Calls `edifis:onboard-school {code} --name= --principal-email=`
  - Extracts claim code from command output
  - Marks request `approved` with `approved_by`, `approved_at`, `claim_code`
  - Emails claim code to principal via `OnboardingApproved` mailable (real `Mail::to()` — not logged)
  - Writes audit entry (`school_request.approve`)
- `school_requests` migration + `SchoolRequest` model
- `ApproveSchoolRequest` action + `OnboardingApproved` mailable
- `PublicWebsiteController` (landing + submit) + `OnboardingController` (PEA admin)
- Tests: submit request (201), duplicate pending (already_submitted), approve → tenant created, domain created, claim code emailed via Mail::fake assert
