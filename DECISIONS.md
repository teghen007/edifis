# DECISIONS — Architecture Decision Records (LOCKED)

These decisions are **frozen**. The builder AI implements against them and does not re-open them. Only the architect (via review) may change a decision, and a change must be recorded here with a new revision and a reason.

Each ADR: **Decision · Why · Consequences for code.**

---

## ADR-001 — Spec-first monorepo, contracts as source of truth
**Decision:** One repository, five projects. `edifis-contracts/` (OpenAPI + JSON Schema) is the single source of truth for all API shapes and event schemas; backend and mobile both implement against it.
**Why:** Two AIs building two apps will drift unless one artifact defines the wire format. Contracts-first makes drift detectable (schema validation) instead of discovered in the field.
**Consequences:** No endpoint or DTO may be added in backend/mobile without first existing in `edifis-contracts/`. CI validates both sides against the schemas.

## ADR-002 — Append-only event sourcing for money & accountability
**Decision:** Ledger, issuance, attendance, and audit data are insert-only event logs. No UPDATE/DELETE. Corrections are new events. Balances/totals are derived sums.
**Why:** White paper's central principle — removes the hardest sync conflicts, prevents double-posting, yields a tamper-evident audit trail.
**Consequences:** These tables have no Eloquent `update`/`delete` paths; migrations add no nullable "edited_at". Repositories expose `append()` and `void(reason)` only.

## ADR-003 — UUIDv7 primary keys everywhere
**Decision:** All PKs are UUID (v7 where ordering matters). No auto-increment integers on synced tables.
**Why:** Offline-created rows must never collide with cloud rows; v7 keeps inserts index-friendly.
**Consequences:** Models use UUID PKs; the mobile local DB mints the same UUIDs so an event keeps its identity from creation through sync.

## ADR-004 — Same codebase, two run modes via `.env`
**Decision:** Cloud Brain and Lite Local Node are the *same* Laravel app. Multi-tenancy + parent portal + payment APIs are toggled off on the local node via `.env` (`EDIFIS_MODE=local|cloud`).
**Why:** Avoids a code fork; a single test suite covers both.
**Consequences:** Feature availability is gated by a single mode flag and capability checks, not by separate builds.

## ADR-005 — Multi-tenancy via stancl/tenancy (DB-per-tenant on cloud)
**Decision:** Cloud uses `stancl/tenancy` (Tenancy for Laravel, v3.x — DB-per-tenant, Laravel 11 compatible); local node runs single-tenant (tenancy stripped). *(Revised: the original draft named `archtechx/tenancy`, which does not exist on Packagist — verified 404. `stancl/tenancy` is the canonical DB-per-tenant package and the intended one.)*
**Why:** Matches white paper; isolates each school's data on the cloud while keeping the node lean.
**Consequences:** Domain code must be tenancy-aware on cloud and tenancy-agnostic on node — abstract tenant context behind a service, never hard-code `tenant()` in domain logic.

## ADR-006 — Auth: Laravel Sanctum tokens, short TTL + revocation list
**Decision:** Sanctum personal-access tokens. Short TTL; cached for brief offline use; nodes pull a revocation list at sync. Roles via Spatie Laravel-Permission.
**Why:** Offline-first means revocation cannot be instant on disconnected nodes; TTL + revocation list bounds the exposure window honestly.
**Consequences:** No code or doc may claim instant global revocation. Token validation checks the revocation list pulled at last sync.

## ADR-007 — Sync: delta payloads, Horizon/Redis queue, idempotency keys
**Decision:** Bidirectional sync exchanges only records changed since last successful sync. Server queues via Horizon/Redis, enforces per-IP rate limits (429), clients use exponential backoff + jitter. Every record carries UUID + revision idempotency key. Accountability events sync on a priority lane.
**Why:** Thundering-herd mitigation + safe retries.
**Consequences:** Every sync handler is idempotent by construction; a replayed batch is a no-op. The sync envelope is defined in `edifis-contracts/`.

## ADR-008 — Per-record marks ownership; cloud-wins only for true conflicts
**Decision:** Each mark is owned by the teacher of record. Cloud-authoritative applies only when two nodes edit the *same* mark; the rejected edit is logged and surfaced, never silently dropped.
**Why:** Teachers work offline on the node; naive "cloud always wins" would clobber normal entry.
**Consequences:** The marks sync resolver compares record ownership + revision lineage, not just arrival time.

## ADR-009 — Mobile: Flutter + Riverpod + Dio + Drift, offline outbox pattern
**Decision:** Flutter app uses Riverpod (state), Dio (HTTP), Drift/SQLite (local store). All mutations write to a local **outbox** of events first, then sync; reads come from the local store, refreshed on sync.
**Why:** Offline-first UX: actions must succeed without connectivity and reconcile later.
**Consequences:** No screen calls the API directly for writes; everything goes through the outbox repository so ret/replay is uniform.

## ADR-010 — No student biometrics; QR + signature; policy-based attendance integrity
**Decision:** Identity/attendance use QR ID cards + on-screen signatures. No fingerprints. Attendance integrity is a discipline policy (count reconciliation), with a default-on, audit-logged teacher override for present-but-cardless students.
**Why:** Legal weight of minors' biometrics; QR tolerates dust/poor light; policy is simpler than enforcement software.
**Consequences:** No biometric capture code anywhere. The override writes an auditable `add-with-reason` attendance event.

## ADR-011 — Mobile money deferred
**Decision:** MTN MoMo / Orange Money are out of the initial build. The append-only ledger is built so they can be added later without rework.
**Why:** Scope control; reconciliation is a subsystem of its own.
**Consequences:** No payment-gateway code now, but the ledger must already be idempotent and reconciliation-ready.

## ADR-016 — Two clients: web (offline-capable via node) + online Flutter native app (all roles, push)  *(revised 2026-06-17)*
**Decision:** EDIFIS has two complementary clients:
1. **Web platform** (Filament + Livewire + Blade) inside `edifis-backend`, served by **both** node and cloud — the full staff workspace **and** the field workflows (QR attendance + classroom issuance, built as browser camera-scan + signature-pad pages). This is the **offline-capable path**: during an internet outage staff use the browser against `https://<school>.local`.
2. **Flutter native app** (Android/iOS/desktop), **online-only** (talks to the cloud), for **all roles** — valued for native speed and **reliable push notifications (FCM)**, especially for parents who prefer a fast app over the browser. **It has NO offline outbox** — if the internet is down, the user falls back to the browser/local node.
**Why:** The offline story is owned by the node + browser, so the Flutter app does not need an offline buffer — which removes its riskiest code. Attendance is a ~20-minute activity; an internet outage simply means switching to the browser on the local server. The app's job is convenience + native push, not offline resilience.
**Consequences:** On campus, write to `.local` first (outage-resilient), syncing up to the cloud; off campus / in the Flutter app, write to the cloud, syncing down. The Flutter scope **drops the Drift outbox/offline-sync** and becomes a thin online client over the cloud API + FCM push. **Node uptime (UPS/auto-restart/cold-spare) is load-bearing** for the offline path. All UI calls the same `Domain/*` Actions. Parents may use the Flutter app (FCM push) or the web portal (Web Push) — both cloud-only.

## ADR-017 — Parents are cloud-direct clients (no node access)  *(2026-06-17)*
**Decision:** Parents/guardians authenticate to and read from the **Cloud Brain only**, via a lightweight mobile-first **Livewire/Blade parent portal** (installable as a PWA). They never connect to a campus node.
**Why:** Parents are off-campus; the cloud holds authoritative parent-facing data (white paper §2) and is the always-on host required for push. Node access would be unreachable from home and pointless.
**Consequences:** Parent portal routes + auth live on the cloud profile only (gated by `EDIFIS_MODE=cloud` / a `ModeGate` feature check). Guardian accounts are bootstrapped by the claim-code flow (T-5.1). The node never serves parents.

## ADR-018 — Notifications via Laravel Notifications; Web Push + in-portal + SMS-fallback  *(2026-06-17)*
**Decision:** Parent notifications use **Laravel Notifications**. **Pilot channels: FCM (push to the Flutter native app), Web Push (VAPID, for the web portal), and database/Reverb (in-portal feed). NO SMS in the pilot** — SMS stays a future channel behind an interface stub. A guardian's notification routes to FCM if they use the app, Web Push if they use the portal (token presence decides). Notifications are generated **on the cloud** at the point data becomes parent-authoritative (results published, fee/issue posted, attendance flag, exeat, calendar event).
**Why:** One unified dispatch, many channels, matched to the device reality. Web Push is free and works backgrounded; SMS reaches everyone but costs money (so deferred/gated). Triggering on the cloud avoids a node trying to reach parents it can't see.
**Consequences:** A `PushSubscription` model (endpoint + keys, bound to a user) + VAPID keys in `.env`; PWA manifest + service worker. Domain Actions on the cloud (e.g. `PublishResults`) dispatch typed Notification classes. SMS channel is a stub interface now, wired to a Cameroon-friendly aggregator later (like mobile-money, deferred but designed-for).

## ADR-019 — Parent auth: PIN bootstrap + new-device email-OTP for all users (no IMEI)  *(2026-06-17)*
**Decision:** Parents log in with **phone number** as username; bootstrap credential = the **phone digits reversed**, with a **forced reset to a 4–6 digit PIN** on first login (friendlier than a password for this audience). **All users (staff + parents) pass a new-device check:** a login from an untrusted device triggers a **6-digit email OTP**; on success a signed **trusted-device cookie (~90 days)** is set so known devices skip OTP thereafter. Device identity is this per-device signed token — **NOT IMEI** (web apps cannot read IMEI/hardware IDs). Parents without an email fall back to PIN + login rate-limiting only. **No SMS in the pilot.**
**Why:** Minimal friction for low-literacy/feature-constrained parents while keeping a real second factor (email OTP on a new device) as the actual gate; the PIN is just convenience. The portal is read-only and low-stakes, so security is right-sized for the pilot and hardened later (SMS OTP, etc.). IMEI is simply not available to a browser.
**Consequences:** new tables `trusted_devices` (user_id, token_hash, last_seen_at, expires_at) and `login_otps` (user_id, code_hash, expires_at, attempts); a `must_reset_credential` flag + `pin_hash` on parent accounts (username = phone); rate-limit PIN/login attempts; staff keep their Filament password **plus** the same new-device OTP. Email is required for OTP — capture a guardian email at enrolment where possible; email-less guardians are PIN-only. Applies on both staff (Filament) and parent (Livewire) login paths.

## ADR-012 — Database engine: PostgreSQL 16 (cloud + node)  *(revised 2026-06-17, was MySQL 8)*
**Decision:** PostgreSQL 16 as the primary store. Redis for queue/cache only. `stancl/tenancy` supported (DB-per-tenant; schema-per-tenant is an available PG optimisation, not adopted yet).
**Why:** EDIFIS is append-only/event-sourced, JSON-heavy (consent scope, audit before/after, sync payloads), and audit-centric — exactly Postgres's strengths (`JSONB`, stronger constraints, partial/expression indexes, mature MVCC for sync bursts). The original MySQL choice was justified by the UnifiedTransform base, but that scaffold was never actually forked (the app is clean Laravel 11 with portable migrations), so the lock-in reason was moot. Switched while pre-production (no real data) — the cheapest possible moment.
**Consequences:** `DB_CONNECTION=pgsql`; Docker images use `postgres:16` + `pdo_pgsql`. Migrations target Postgres: **no `unsigned` integer types** (use `bigInteger`/`integer`, optionally `CHECK (col >= 0)`); prefer `jsonb` for JSON columns; respect Postgres strict `GROUP BY`. Re-green the full suite on Postgres in the Docker image (T-8.0). Operator note: the schools' IT teachers should be oriented to `pgAdmin`/`psql` since MySQL/phpMyAdmin is more common locally — fold into go-live training.

## ADR-013 — Staff-hierarchy roles; NO student accounts
**Decision:** App users are the staff hierarchy + parents. The eight roles are: `principal`, `vice_principal`, `bursar`, `class_master`, `subject_teacher`, `discipline_master`, `secretary`, `parent`. There is **no student role** — minors do not have accounts; their QR ID card is a physical card only.
**Why:** Students are minors not expected to carry phones; removing every minor login strengthens compliance with Cameroon Law No. 2024/017 and simplifies access control. White paper §7.
**Consequences:** The role enum in `edifis-contracts` is exactly these eight. Student-self-service screens do not exist; results/attendance reach families through the parent role. Spatie permissions are scoped (a Class Master/Subject Teacher sees only their own class/subjects).

## ADR-014 — VACUUM: Principal command mode + AI co-pilot (unlimited reach, never invisible)
**Decision:** A Principal-only capability, `VACUUM`, with (a) a read-only natural-language AI co-pilot over the school's academic data and (b) audited command authority to correct any mark, promote/repeat any student, override the engine, and deactivate accounts. **Every VACUUM action writes an immutable `audit_entry` (actor, time, before/after, reason).** Finance is never directly editable via VACUUM; "delete" means "deactivate". Bulk/destructive commands require explicit confirmation. Capability is gated to the Principal role and toggleable per school.
**Why:** The Principal legitimately needs institution-wide authority and instant insight. The system's entire value is tamper-evidence, so power must come *with* a trail, not by bypassing it — this protects the Principal as much as the institution. White paper §7.1.
**Consequences:** No code path performs a silent/unaudited write. `/vacuum/command` refuses finance targets (`forbidden`) and refuses non-Principal callers. Mark corrections via VACUUM use the same revisioned, audited mechanism as ADR-008. The AI co-pilot reads only data the Principal may already see; it never fabricates records.

## ADR-015 — Timetables & calendar of activities
**Decision:** Two scheduling modules. The **master timetable** is authored by the VP/timetable officer and approved by the Principal; the **calendar of activities** is maintained by Principal/VP/Secretary and is school-wide. Both are role-scoped on read and served from the local node when offline.
**Why:** Scheduling is core school operations and was missing. White paper §7.2.
**Consequences:** New `Domain/Timetable` module + `/timetable` endpoints in the contract; home screens and Reverb notifications consume them.
