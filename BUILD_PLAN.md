# BUILD_PLAN — EDIFIS

The full phased task list. **Builder AI: act only on tasks marked `[READY]`.** `[BLOCKED]` waits on a dependency or an architect decision; `[DONE]` is finished and recorded in `PROGRESS.md`.

Task ID format `T-<phase>.<n>`. Read each task's `Specs` before coding and satisfy every `DoD` bullet. See `AGENT_GUIDE.md` for the operating loop and the global Definition of Done.

**Legend:** `[READY]` work now · `[BLOCKED]` not yet · `[DONE]` complete · `[REVIEW]` awaiting architect sign-off.

---

## Phase 0 — Foundations & Contracts  *(current)*

Goal: the wire format and both project skeletons exist and compile/boot empty, so all later work targets a fixed contract.

### T-0.1 Finalize shared contracts  `[READY]`
- **Project:** edifis-contracts
- **Depends:** —
- **Specs:** `edifis-contracts/README.md`, `edifis-contracts/schemas/*.json`, `edifis-contracts/openapi/edifis.openapi.yaml`
- **Output:** completed JSON Schemas for every event + entity; OpenAPI paths for auth, sync, issuance, attendance, academics, fees, students/consent; the shared error model; the sync-envelope schema.
- **DoD:**
  - Every event in white-paper §15 (Appendix A) has a JSON Schema with required fields, types, enums.
  - OpenAPI validates (`spectral lint` or equivalent) with zero errors.
  - Error model defines codes for: validation, auth/expired-token, revoked-token, conflict, idempotency-replay, rate-limited.
  - Sync envelope defines: delta cursor, batch, idempotency key, priority lane flag.
- **Must-test:** schema examples validate against their schemas (a tiny validation script).

### T-0.2 Backend skeleton boots  `[READY]`
- **Project:** edifis-backend
- **Depends:** —
- **Specs:** `edifis-backend/README.md`, `edifis-backend/SPECS/00-overview.md`, `DECISIONS.md` (ADR-004/005/012)
- **Output:** a Laravel 11 app that boots in both `EDIFIS_MODE=cloud` and `=local`; Docker dev stack up; Pint + PHPStan + Pest configured; empty health endpoint.
- **DoD:**
  - `composer install` + `php artisan serve` (or Octane) boots with the committed `.env.example`.
  - `GET /api/health` returns `{status, mode, version}` reflecting the `.env` mode.
  - Pint, PHPStan (≥ level 6), Pest all run green on the empty app.
- **Must-test:** health endpoint returns correct mode for each `.env` value.

### T-0.3 Mobile skeleton boots  `[READY]`
- **Project:** edifis-mobile
- **Depends:** —
- **Specs:** `edifis-mobile/README.md`, `edifis-mobile/SPECS/00-overview.md`
- **Output:** a Flutter app that runs to a role-router placeholder; Riverpod + Dio + Drift wired; `flutter analyze` clean; a configured Dio pointing at `--dart-define` base URLs (local + cloud).
- **DoD:**
  - `flutter run` shows a launch screen that branches to a placeholder per role.
  - Drift DB opens; an empty `outbox` table exists.
  - `flutter analyze` and `dart format --set-exit-if-changed` pass.
- **Must-test:** a widget test that the role router renders the right placeholder for a given fake role.

### T-0.4 Infra dev stack  `[READY]`
- **Project:** edifis-infra
- **Depends:** —
- **Specs:** `edifis-infra/README.md`
- **Output:** `docker-compose.yml` for local dev (app + PostgreSQL 16 + Redis), and a separate `local-node` compose that adds the node-mode env. Example hostapd/dnsmasq configs as `.example`.
- **DoD:**
  - `docker compose up` brings the backend dev stack healthy.
  - Cloud vs local-node differences are env-driven, not separate images.
- **Must-test:** a smoke script that curls the backend health endpoint through the compose network.

---

## Phase 1 — Identity, Tenancy, Auth  `[READY]`  *(released 2026-06-17)*

Goal: a user can authenticate, carry a role, and operate within a tenant (cloud) or single school (node).

- **T-1.1** Tenancy bootstrap (stancl/tenancy) + mode gating (ADR-004/005)
- **T-1.2** User + Spatie roles/permissions; the **eight-role** staff hierarchy + parent, **no student role** (ADR-013, white-paper §7)
- **T-1.3** Sanctum tokens: short TTL, offline caching contract, revocation list pull-at-sync (ADR-006)
- **T-1.4** Master PEA ID issuance + student identity model + consent capture (white-paper §8.1)
- **T-1.5** Mobile auth feature: login, token cache, offline read, role routing
- **T-1.6** Staff offboarding flow: cloud-immediate, node-eventual, audit continuity (§14.6)

## Phase 2 — The Event Backbone (Ledger, Issuance, Attendance, Audit)  `[READY]`  *(released 2026-06-17)*

> **Architect note — build order matters here.** Do **T-2.1 first** (the shared primitives: `HasUuidV7`, `AppendOnlyRepository`, `Idempotency`, `AuditLogger` — `ClockService` already exists from Phase 0). Everything else reuses them. Backend tasks T-2.1→T-2.4 are fully verifiable in the 8.3 Docker image — do those before the mobile UIs (T-2.5/T-2.6), which stay `[VERIFY-PENDING]` until Flutter is available. Non-negotiables for this phase: append-only (a reflection test proving no `update`/`delete` on event repos), derived balances (a test proving balance is never a stored column — only `SUM`), idempotency (replay of a batch/scan = no-op), integer CFA minor units (never float).

Goal: the append-only spine the whole system rests on.

- **T-2.1** `AppendOnlyRepository` base + audit-log event (ADR-002, invariants §4)
- **T-2.2** Issuance: catalogue import (Excel), default-rubric-per-form, issue-by-exception, one-signature/many-events
- **T-2.3** Ledger: auto-post on issue-event, derived balance, return/void events, no-double-post
- **T-2.4** Attendance: session model, QR scan events, default-on override, count reconciliation
- **T-2.5** Mobile issuance UI (rubric checklist + signature pad → outbox events)
- **T-2.6** Mobile attendance UI (QR scan, live count vs headcount, override, print/register export)

## Phase 3 — Synchronization  `[READY]`  *(released 2026-06-17)*

> **Architect note — carry-over fixes from the Phase 2 review (do these as part of Phase 3):**
> 1. **BLOCKING — T-3.2.0: make `Idempotency::applyOnce` atomic & claim-first** (exact pattern + concurrency tests in backend SPEC 03 §2). The current check-then-act, non-transactional version double-posts under concurrent replay / mid-batch crash. This is the foundation sync rests on — fix it before writing any sync apply, and re-point `IssueItemsToStudent` + `RecordScan` at it.
> 2. **Required cleanup — `AppendOnlyRepository::void()` is attendance-shaped.** The base `void()` hardcodes attendance columns (session_id, scanned_at, scanned_by, source); calling it on `IssueEventRepository` would write/omit the wrong columns. It only works today because issuance uses `ReturnItem` instead. Make `void()` **abstract** (or override per-repo) so the base class knows nothing about a specific event's columns.
> 3. **Minor — drop or demote `attendance_sessions.scanned_count`.** It's a stored aggregate of append-only events that `SessionTally` (correctly) does not use as truth, and it drifts (voids don't decrement it). Either remove the column or document it explicitly as a non-authoritative cache. Same family as "never store a derived total."

Goal: bidirectional, idempotent, conflict-aware sync between node and cloud.

- **T-3.1** Sync envelope + delta cursor server endpoints (ADR-007)
- **T-3.2** Idempotent apply (replay = no-op) for all event types
- **T-3.3** Per-type conflict resolution: append-only / marks-ownership / attendance / LWW-demographics (ADR-008, §5.1)
- **T-3.4** Horizon/Redis queueing, rate-limit (429), backoff+jitter, priority lane for accountability
- **T-3.5** Mobile outbox drain + delta pull + revocation-list pull; clock-discipline restamp on sync

## Phase 4 — Academics, Promotion, Documents  `[READY]`  *(released 2026-06-17)*

> **Architect note — carry-over from the Phase 3 review (REQUIRED in T-4.1):** fix `ConflictResolver::resolveMark` — it is (1) not idempotent (replayed mark edit → spurious `cloud_wins` conflict) and (2) writes no audit (raw `update()`; the cloud-side cloud-wins decision logs nothing). Build `RecordMark` as the single audited mark-write path and route the resolver's linear-edit apply through it; on `cloud_wins`, append a `mark.conflict` audit entry AND persist the conflict so an offline teacher receives it on pull. Exact requirements + tests in backend SPEC 04 §1. The other sync paths (append-only/LWW/consent) and the atomicity fix passed review and need no change.

Goal: marks → report cards → promotion, in the school's real formats.

- **T-4.1** Marks entry (per-record ownership), sequences/terms, coefficient model
- **T-4.2** Promotion engine: coefficient balancing, baseline, principal override, rule-set versioning (§6)
- **T-4.3** Document outputs: report cards, mark sheets/broadsheets, transcripts, receipts, registers (DOMPDF) validated against real samples in `RESOURCES/`
- **T-4.4** Mobile academics (teacher marks entry) + parent results/attendance views, publish-gated visibility (no student views, ADR-013)

## Phase 6 — Timetable/Calendar & VACUUM  `[READY]`  *(released 2026-06-17)*

Goal: scheduling + the Principal's command mode. Backend SPEC 06, mobile SPEC 05.

- **T-6.1** Timetable & calendar modules: VP authors, Principal approves, role-scoped reads (ADR-015, §7.2)
- **T-6.2** VACUUM backend: read-only AI co-pilot (`/vacuum/query`) + audited command authority (`/vacuum/command`), finance excluded, deactivate-not-delete, Principal-only (ADR-014, §7.1)
- **T-6.3** Mobile timetable/calendar views (role-scoped, offline) + VP authoring
- **T-6.4** Mobile VACUUM shell: AI co-pilot chat + command sheet with mandatory reason + confirm; audit surfaced

## Phase 5 — Compliance, Ops, Go-Live  `[READY]`  *(released 2026-06-17)*

> **Architect note — three carry-over fixes from the Phase 4 review (do these alongside Phase 5; #1 is priority):**
> 1. 🔴 **Implement `ApplyEnvelope::pull()` for real** (backend SPEC 03 §1). It's currently a stub returning empty deltas, so cloud→node propagation and conflict delivery don't work — persisted `mark_conflicts` never reach the owning teacher. This is the missing half of sync and gates go-live.
> 2. 🟡 **Make the divergent mark-conflict path idempotent** (backend SPEC 03 §3). A retried rejected edit currently duplicates `mark_conflicts` rows + `mark.conflict` audit entries. Guard by `(mark_id, rejected_revision)` or wrap `resolveMark` in `applyOnce`.
> 3. 🟡 **Fix `ComputePromotion` year selection** (backend SPEC 04 §2). `sequence LIKE 'YYYY%'` matches nothing on real sequence names; add a proper `academic_year` linkage and test with realistic sequences.

Goal: the operational layer that makes it survivable in the field.

- **T-5.1** Account provisioning + credential bootstrapping (staff/student/guardian claim codes) (§14.3)
- **T-5.2** Data migration pipeline: validating Excel import with dry-run + reconciliation (§14.2)
- **T-5.3** Backup/restore runbook automation + node-failure swap procedure (§14.4)
- **T-5.4** Node/UPS telemetry → central monitoring endpoint (§2.1, §11)
- **T-5.5** Go-live checklist verification + document-format sign-off

---

## Phase 7 — Hardening & Go-Live Correctness  `[READY]`  *(released 2026-06-17 · GO-LIVE GATING — must precede any production deployment)*

Found across the Phase 5 + Phase 6 reviews: sync `pull()` keys deltas off `created_at` and delivers conflicts unreliably, and the VACUUM audit trail is incomplete. These cause silent divergence under real intermittent connectivity and undermine the tamper-evidence guarantee. Specs: backend SPEC 03 §3b, SPEC 06 §2.

**Sync (SPEC 03 §3b):**
- **T-7.1** Stamp an authoritative `synced_time` (or monotonic server sequence/ULID) when the cloud applies/persists any synced record; build pull deltas off it, not `created_at`.
- **T-7.2** Unify cursor semantics on that single monotonic watermark (push currently stores `revision`, pull expects a timestamp).
- **T-7.3** Target `pullConflicts` by the owning teacher's node/user; never deliver another node's conflicts.
- **T-7.4** At-least-once conflict delivery: mark `pulled_at` only after client ack, redeliver until acknowledged.

**VACUUM audit (SPEC 06 §2 — ADR-014 "power with a trail"):**
- **T-7.5** Persist the `reason` in every vacuum `audit_entry`; thread it through `RecordMark` (optional reason) and add a `reason` column to revocations. No VACUUM action may lose its reason.
- **T-7.6** Real before/after snapshots (actual entity state, not `{target, at}`); fix `requireConfirm` to classify `deactivate_account`/bulk as requiring confirm; return contract code `forbidden` (not `node_mode_unsupported`) for non-principal/finance.

- **Must-test:** a late-synced record (old `created_at`, newer `synced_time`) appears in pull; conflicts reach only their owning node and survive a dropped response; every VACUUM command's audit carries the reason + true old→new; `deactivate_account` without confirm → `validation_failed`; non-principal/finance → `forbidden`.

### T-7.7 `synced_time` on marks  `[READY]`  *(PRODUCTION-BLOCKING — found in the Phase 7 final review)*
- `RecordMark` never sets `synced_time`, so synced marks have `synced_time = null` and pull's `synced_time > cursor` filter excludes them forever → **marks never propagate cloud→node.** Thread `synced_time` into `RecordMark` (create + update) or set it in `resolveMark`. Also unify the cursor format (push stores µs-int, pull emits ISO datetime). Spec: SPEC 03 §3b.
- **Must-test:** a mark applied via sync has non-null `synced_time` and appears in a later pull on another node.

## Phase 8 — Local Test Lab Enablement & Operational Readiness  `[READY]`  *(released 2026-06-17)*

Goal: make the Docker lab (`edifis-infra/lab/`) run end-to-end and clear the operational go-live caveats. The lab compose + envs + runbook already exist; these are the backend pieces it needs.

- **T-8.0** **Migrate the codebase to PostgreSQL 16 (BLOCKING — do first; ADR-012 revised).** Infra/config already switched (Docker images → `postgres:16`/`pdo_pgsql`, `.env*` → `pgsql`/5432, lab compose + entrypoint). Code side: (a) replace all `unsignedBigInteger`/`unsignedInteger` with `bigInteger`/`integer` (optionally add `CHECK (col >= 0)`) — affects e.g. `catalogue_items.cost`, `issue_events.cost`, `consents.version`; (b) switch JSON columns to `jsonb`; (c) audit for any MySQL-only SQL and Postgres strict-`GROUP BY` issues (e.g. `QueryPlanner::queryTopStudents`); (d) point the test suite at Postgres (run in the lab image, not SQLite). **Must:** full suite green on PostgreSQL 16 in the 8.3 Docker image. Until this is green, nothing else in Phase 8 is trustworthy.
- **T-8.1** `LabSeeder` — seeds roles/permissions, the two school tenants (Nkwen, Mankon), demo staff (incl. a Principal for VACUUM, a bursar, a class master), and a little academic + fee + catalogue data. Prints demo logins. Idempotent (safe to re-run).
- **T-8.2** **Node→cloud sync runner** `php artisan edifis:sync` — the server-side *initiator* the lab calls. Collects this node's records changed since its last cursor, POSTs a `SyncEnvelope{direction:push}` to `SYNC_CLOUD_BASE_URL/sync`, then pulls deltas + conflicts + the revocation list and applies them locally. Schedulable (runs on connectivity). This is distinct from the cloud's receiving `/sync` endpoint (already built) — surfaced by the lab because node-initiated sync had no driver. **Must-test:** offline-created events on a node land on the cloud after `edifis:sync`; a cloud-side change is pulled back; replay is a no-op.
- **T-8.3** Run the full backend suite in the **PHP 8.3 + PostgreSQL 16 lab image** (`docker compose ... exec cloud-app pest/pint/phpstan`); fix anything that only surfaces on real Postgres (JSON/`jsonb` columns, strict `GROUP BY`, `SUM`/numeric typing, timestamp precision). Clears the "verified on SQLite only" caveat.
- **T-8.4** **DOMPDF templates** — real report-card + fee-receipt + register PDFs, validated against the sample report cards in `/RESOURCES/*.pdf` (T-4.3 produced structured data only). Match the school's real layout/coefficient structure.
- **T-8.5** **Backup/restore drill** — exercise the runbook (white-paper §14.4): automated encrypted backup of a node DB + files, then a timed restore into a scratch DB, asserting counts/balances reconcile. Document the node-failure swap.

## Phase 9 — Web Staff Workspace (Filament + Livewire)  `[READY]`  *(ADR-016; spec: edifis-backend/SPECS/07-web-frontend.md)*

Goal: server-rendered staff UI inside `edifis-backend`, served by BOTH node and cloud, calling the same `Domain/*` Actions. No business logic in Blade/Livewire.

- **T-9.1** Filament install + auth (Sanctum/session) + Spatie-role-gated panel access; a staff panel reachable on node (`*.local`) and cloud.
- **T-9.2** Filament Resources for the connected workflows: students/enrolment+consent, fees/issuance (rubric checklist + signature pad as a Livewire component), marks entry (per-record ownership), documents (report card/receipt/register print via existing DOMPDF), timetable/calendar author+approve.
- **T-9.3** **VACUUM Filament page** (Principal only): AI co-pilot query box (`/vacuum/query`) + audited command panel (`/vacuum/command`) with mandatory reason + confirm; surfaces the audit result. Reuse `RunQuery`/`RunCommand`.
- **T-9.4** Role-scoped visibility in every Resource (class_master/subject_teacher see only their classes/subjects); finance hidden from non-bursar; no student role anywhere.
- **Must-test (Pest + Livewire test helpers):** each role sees only its permitted Resources/actions; a Filament action calls the Domain Action (not raw Eloquent); VACUUM page rejects non-principal; runs in both `EDIFIS_MODE` values.

## Phase 10 — Parent Portal + Notifications (cloud-direct)  `[READY]`  *(ADR-017/018/019; spec: SPECS/08-parent-portal-notifications.md)*

Goal: a tiny, mobile-first Livewire/Blade parent portal on the **cloud only**, installable as a PWA, with push. **Pilot scope: notifications + the guardian's own child's info. No SMS. Keep it small.**

- **T-10.1** Parent auth (ADR-019): username = phone; bootstrap = phone reversed; **force a 4–6 digit PIN on first login**; rate-limit. `ModeGate` gates the portal to `cloud`. Read-only.
- **T-10.1b** **New-device email OTP for ALL users** (staff + parents): untrusted device → 6-digit email OTP → set a ~90d trusted-device cookie. Device identity = signed token, **not IMEI**. Email-less guardians = PIN-only.
- **T-10.2** Portal screens (minimal): per-child — balance, published results, attendance summary, receipts/report-card downloads, notices/calendar. Guardian sees only their children. Nothing more.
- **T-10.3** PWA: `manifest.webmanifest` + `sw.js` → installable + receives Web Push.
- **T-10.4** Notifications — **pilot channels: Web Push + database/Reverb only**. `PushSubscription` model (VAPID); typed classes (`ResultsPublished`, `FeePosted`, `AttendanceFlagged`, `ExeatIssued`, `CalendarEventPosted`) with `toWebPush`/`toDatabase`.
- **T-10.5** Triggers: cloud-side Domain Actions dispatch to the affected child's guardians (e.g. `PublishResults`). Idempotent (no double-notify on sync replay).
- **~~T-10.6 SMS~~ — OUT OF PILOT** (ADR-018). Empty `SmsChannel` interface stub only; do NOT wire it or add it to any `via()`.
- **Must-test:** phone-reversed bootstrap forces PIN; new device → OTP, trusted device skips; PIN/login rate-limited; guardian sees only their children; `PublishResults` notifies exactly those guardians once (web-push + DB), **no SMS**; parent routes 404 in `local` mode; PWA served.

## Phase 10 — Go-Live fixes (carry-over from review)  `[READY]`  *(before parent portal goes live; non-blocking for Phase 11)*
- **T-10.7** Harden `TrustDevice`: cookie holds a `random_bytes(32)` secret; DB stores only `hash('sha256', secret)`; `isTrusted()` looks up by **user_id + hash** (bind to the authenticating user). A DB leak must NOT yield usable device cookies. Test: a stored device row can't be replayed as a cookie; another user's token doesn't grant trust.
- **T-10.8** Wire real OTP email (`Mail::to($user->email)->send(new OtpMail($code))`); keep the log line only in `local`/testing. Test: OTP mail is queued to the user's email.
- **T-10.9** Ensure the PWA has a real HTML `/parent` shell (Blade/Livewire) that the service worker caches and that renders the child dashboard — not just JSON endpoints.

## Phase 11 — Web Field Workflows (attendance + issuance)  `[READY]`  *(ADR-016 revised; web on the node)*

Goal: build the two field workflows as **browser features in the Filament/Livewire web app**, served node-first on campus. No Flutter for the pilot.

- **T-11.1** **Web QR attendance** (Livewire page): open a class session; **browser camera** QR scan (`getUserMedia` + a JS QR lib like `html5-qrcode`/`zxing`); live count vs teacher headcount; **default-on manual override** (cardless present student, audited); close session; print register. Writes via the existing `OpenSession`/`RecordScan`/`CloseSession` Actions. Idempotent per session+student.
- **T-11.2** **Web issuance** (Livewire page): pull student (QR or pick) → default rubric pre-checked → uncheck not-received → running total → **on-screen signature pad** (canvas) → one signature, N `issue_event`s via `IssueItemsToStudent`; ledger auto-posts. Return via `ReturnItem`.
- **T-11.3** **Node-first routing:** on campus the page targets `.local` (resilient to internet loss); off campus the cloud. Document the operational dependency: no client offline buffer — node must be up (UPS/auto-restart/cold-spare).
- **T-11.4** Role scoping: class_master/subject_teacher attendance for their classes; bursar for issuance; append-only invariants intact.
- **Must-test (Pest + Livewire):** a scan writes one idempotent `attendance_event`; duplicate scan = no double; override requires a reason; an N-item issue writes N events + N ledger debits under one signature; replay-safe; role-gated. Run in the lab Postgres image.

### Phase 12 correction (from review — REQUIRED before push works)
- **T-12.6 FCM uses a dead endpoint.** `FcmChannel` posts to the **legacy** `https://fcm.googleapis.com/fcm/send` with `Authorization: key=<server_key>` — Google **shut that API down in June 2024**; it will not deliver. Switch to **FCM HTTP v1** (`https://fcm.googleapis.com/v1/projects/{PROJECT_ID}/messages:send` + OAuth2 Bearer from a service-account JSON). Use `laravel-notification-channels/fcm` (handles v1 + OAuth2) or implement v1 directly. Config via `.env` (service-account path / project id), not a static server key. Test: the channel builds a v1 request to the right URL with a Bearer token (Http::fake assertion).

### Phase 11 corrections (from review — REQUIRED before field go-live)
- **T-11.5 Real signature capture.** Issuance currently sets `signature_ref = 'sig-'.batchId` (a synthetic string; the canvas `$signatureData` is discarded). Capture the canvas as a base64 PNG, **persist it** (storage disk or a `signatures` table), and set `signature_ref` to the stored reference. The white-paper issuance accountability (§9.1/§8) depends on a real captured signature. Test: an issue batch stores a non-empty signature image and `signature_ref` resolves to it.
- **T-11.6 Real browser-camera QR scan.** Attendance currently uses keyboard/text entry only. Add real device-camera scanning (`html5-qrcode` or `zxing-js`) that feeds `RecordScan`; keep manual entry as a fallback (USB scanner / typed). Matches §8.2/§9.2 (scan the QR card with the tablet camera). Test/manual: camera scan path records a scan; manual fallback still works.

## Phase 12 — Flutter online native app (all roles, FCM push)  `[READY]`  *(ADR-016 revised; needs Flutter 3.x to verify; iOS needs a Mac → Android first)*

Goal: a thin **online-only** native app over the cloud API + FCM push. **NOT offline** — no Drift outbox (the node+browser owns offline). Valued for native speed + reliable push, especially parents.

- **T-12.1** **Strip the offline layer** from the existing Flutter code: remove Drift outbox/sync-service; all reads/writes go straight to the cloud API via Dio. Keep auth, role routing, Riverpod.
- **T-12.2** Toolchain + codegen: `flutter pub get` → `build_runner` (regenerate DTOs from `edifis-contracts`, drop hand-stubs); `flutter analyze`/`flutter test` clean.
- **T-12.3** **FCM push:** integrate `firebase_messaging`; register the device FCM token against the user on the cloud (a `fcm_tokens` table / extend `PushSubscription`); backend gains an **FCM channel** in Laravel Notifications (the 5 typed classes already exist → add `toFcm`). Parents (and any role) receive native push.
- **T-12.4** Role screens (online): parents → child dashboard + notifications; staff → their role views (read/entry against cloud). Field workflows (attendance/issuance) primarily stay **web** (ADR-016) — the app is for connected use + push.
- **T-12.5** Build a debug **Android APK**, run against the cloud (or lab cloud), log in as a seeded user, confirm a push arrives on `PublishResults`.
- **Must-test:** `flutter analyze`/`flutter test` green; DTOs round-trip the contract examples; an FCM token registers and a `ResultsPublished` notification delivers to the device. (iOS later — needs a Mac.)

## Phase 13 — Seamless school onboarding + production domain  `[READY]`  *(domain: myedifis.com; infra scaffold in `edifis-infra/prod/`)*

Goal: one command/click adds a fully-working school at `https://<code>.myedifis.com`, with HTTPS auto-issued (Caddy on-demand TLS gated by the app).

- **T-13.1** `GET /api/tenancy/domain-allowed?domain=<host>` (public) → `200` only if `<host>` is a **registered tenant domain** (else `404`). This is Caddy's on-demand-TLS `ask` gate — prevents cert-issuance for unknown hosts. Must be fast + side-effect-free.
- **T-13.2** `php artisan edifis:onboard-school {code} --name= --principal-email=` → creates the tenant + domain `<code>.myedifis.com`, runs tenant migrations + role/permission + starter seed, creates the **Principal** with a one-time claim code (forced reset), prints the login URL + claim code. Idempotent (re-running an existing code is a safe no-op / reports status).
- **T-13.3** (optional) A super-admin **Filament "Schools" page** on the central domain to onboard via a form (calls the same Action) + list/suspend schools.
- **T-13.4** Verify stancl/tenancy **domain identification** middleware on subdomains resolves the right tenant; the central domain (`myedifis.com`) serves the landing/central, not a tenant.
- **Must-test:** `domain-allowed` returns 200 for a created tenant, 404 otherwise; `onboard-school` creates a working tenant reachable by its domain with a seeded Principal; central domain is not treated as a tenant.

## Phase 14 — Public website + self-service onboarding requests  `[READY]`  *(central domain myedifis.com, served by the app)*

Goal: a real marketing website on `myedifis.com` where a principal can **request to onboard a school**, and a PEA super-admin **approves** it (which triggers Phase 13's onboarding). Brand green `#1B5E20`.

- **T-14.1 Public marketing pages** (Blade/Livewire on the central domain, no auth): `/` Home (hero + value prop + CTAs), `/features`, `/for-schools`, `/for-parents`, `/about`, `/contact`, `/app` (app download). Mobile-first, lightweight.
- **T-14.2 Onboarding request** `/onboard` — a form (school name, proposed code, principal name/email/phone, location/notes) → creates an `onboarding_requests` row (status `pending`) + notifies PEA admin. Show a confirmation ("we'll review and email you"). Rate-limit + basic anti-spam.
- **T-14.3 PEA super-admin** (Filament on the central domain, restricted to a `pea_admin`/super-admin): an **Onboarding Requests** resource (review → **Approve** runs `edifis:onboard-school` (Phase 13), emails the principal the login URL + claim code → status `live`; or **Reject** with reason) and the **Schools** resource (list/suspend).
- **T-14.4** Central-domain routing: `myedifis.com` serves the website + super-admin; it is **not** a tenant (tenant resolution applies only to subdomains). Parent portal/app links point to the school's subdomain.
- **Must-test:** marketing pages render without auth; an onboarding request is stored + admin-notified; only a PEA super-admin can approve; approval creates a live tenant (Phase 13) and emails the principal; central domain isn't treated as a tenant.

## Backlog / explicitly deferred (do NOT build yet)

- Mobile-money (MTN MoMo / Orange Money) integration + daily reconciliation job (ADR-011).
- Predictive analytics / anomaly detection / ML promotion recommender (future AI surface).
- Parent online payments.

---

## How the architect releases work

After each review, Claude flips the next batch of tasks from `[BLOCKED]` to `[READY]`, possibly adding sub-tasks discovered during the scan. The builder never self-promotes a `[BLOCKED]` task.
