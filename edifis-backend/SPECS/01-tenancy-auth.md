# Backend SPEC 01 — Tenancy, Auth, Students, Consent (Phase 1)

Implements white-paper §6, §7, §8.1, §14.6; ADR-004/005/006/008. Contract: `../edifis-contracts` (auth + students paths, `consent`/`student` schemas, `_error`).

---

## 1. Tenancy & mode (T-1.1)

- Install `stancl/tenancy` (v3). Central domain = `TENANCY_CENTRAL_DOMAIN`. Each school is a tenant (DB-per-tenant on cloud). Use the package's bootstrappers + the `InitializeTenancyByDomain` middleware on cloud routes; on `local` the tenancy middleware is not registered.
- `ModeGate` reads `EDIFIS_MODE`. On `local`: tenancy middleware is bypassed; the node serves exactly one school (`EDIFIS_SCHOOL_CODE`).
- `TenantContext::current()` returns the active school in both modes (real tenant on cloud, the single configured school on node).
- **DoD:** the same Action resolves the right school in both modes with no `EDIFIS_MODE` branches inside Actions.

## 2. Users, roles, permissions (T-1.2)

**Eight roles via Spatie (ADR-013, white-paper §7). NO student role** — minors have no accounts. Define permissions, not just roles, and assign them in a seeder. Role enum (must match the contract exactly): `principal`, `vice_principal`, `bursar`, `class_master`, `subject_teacher`, `discipline_master`, `secretary`, `parent`.

| Role | Key permissions (non-exhaustive) |
|------|----------------------------------|
| principal | `*` within the school **as audited actions**; `vacuum.query`, `vacuum.command`, `promotion.override`, `account.deactivate`. **Deny** silent/unaudited writes, direct finance-ledger edits, cross-school. |
| vice_principal | `timetable.manage`, `calendar.manage`, `academics.view.school`, `attendance.view.school`, `discipline.*`. **Deny** finance edits, `vacuum.*`, cross-school. |
| bursar | `issuance.*`, `fees.*`, `students.register`, `signature.capture`, `documents.print`. **Deny** `marks.lock.edit`, cross-school. |
| class_master | `attendance.*` (own class), `marks.coordinate` (own class), `discipline.record`, `documents.print.ownClass`. **Deny** other classes, finance. |
| subject_teacher | `marks.enter` (own subjects), `attendance.take` (taught classes), `documents.print.ownClasses`. **Deny** other subjects/classes, finance. |
| discipline_master | `discipline.*`, `exeat.*`, `attendance.view`. **Deny** marks edit, finance. |
| secretary | `students.enrol`, `demographics.edit`, `documents.print`. **Deny** marks/finance edits, promotion. |
| parent | `child.balance.view`, `child.results.view`, `child.attendance.view`, `child.documents.download`. |

- Enforce class/subject scoping in policies, not just role checks (a class_master/subject_teacher sees only *their* class/subjects).
- `principal` power is broad but every mutating action routes through the audited VACUUM path (SPEC 06) — there is no unaudited write path for any role.
- **Must-test:** each role is allowed its matrix row and denied the "cannot do" column; a subject_teacher cannot read another class; a non-principal calling a `vacuum.*` ability → `forbidden`; no student role can be created.

## 3. Sanctum auth + revocation (T-1.3)

- `POST /auth/login` issues a token with `expires_at = now + SANCTUM_TOKEN_TTL_MINUTES`.
- Cached-token offline use: a token within `SANCTUM_OFFLINE_GRACE_MINUTES` is accepted offline by the node; re-validated against cloud on reconnect.
- Revocation: disabling a user adds them to a revocation list. `GET /auth/revocations?since=` returns revoked token/user ids. Nodes/apps pull this at sync and reject listed tokens (`token_revoked`).
- **Honest behaviour (ADR-006):** immediate on cloud, eventual on offline nodes bounded by TTL. No code/comment may claim instant global revocation.
- **Must-test:** (a) expired token → `token_expired`; (b) revoked-then-synced token → `token_revoked`; (c) offline within grace → accepted; (d) revocation reaches a node only after a revocations pull.

### Sequence — offboarding (T-1.6)
```
admin disables user U
  -> cloud: U.active=false; append revocation(U, all token_ids), append audit_entry(action=user.disable)
  -> cloud: U's tokens rejected immediately
  -> node (next sync): pulls /auth/revocations; marks U's cached token rejected
  -> U's authored records (marks, issues) are RETAINED (reassignment, not deletion)
```

## 4. Students, Master PEA ID, consent (T-1.4)

- `POST /students` enrols a student: mints internal UUID, the cloud issues the Master PEA ID (`PEA-YYYY-NNNNN`), stores the photo ref, and **requires** a valid `consent` for a minor or returns `consent_required`.
- Consent is versioned (append a new version; never overwrite). A scope change → new consent row.
- Master PEA ID is issued by the cloud only; a node enrolling offline mints a provisional internal id and requests the PEA ID at sync.
- **Must-test:** (a) enrol without consent → `consent_required`; (b) consent scope change creates v2, v1 retained; (c) PEA ID format matches the schema pattern; (d) offline enrolment gets a real PEA ID after sync.

## Outputs (files to create)
```
app/Domain/Tenancy/Services/{ModeGate.php, TenantContext.php}
app/Domain/Auth/{Actions/IssueToken.php, Actions/RevokeUser.php, Services/RevocationList.php, Models/...}
app/Domain/Students/{Actions/EnrolStudent.php, Models/Student.php, Repositories/...}
app/Domain/Consent/{Actions/CaptureConsent.php, Models/Consent.php}
app/Http/Controllers/Api/{AuthController.php, StudentController.php}
database/migrations/*  database/seeders/RolesAndPermissionsSeeder.php
tests/Feature/{AuthTest.php, OffboardingTest.php, EnrolmentConsentTest.php} tests/Unit/*
```
