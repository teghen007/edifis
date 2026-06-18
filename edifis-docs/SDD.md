# EDIFIS — Software Design Document (SDD)

Version 1.0 · implements the SRS and `/DECISIONS.md` (ADR-001…019).

---

## 1. Architecture overview
One codebase, two run modes (ADR-004), two client types (ADR-016):

```
              ┌──────────────────────── CLOUD BRAIN (multi-tenant) ───────────────────────┐
 Parents ───► │  Laravel + Octane · PostgreSQL (DB-per-tenant) · Redis/Horizon · Reverb   │
 (cloud only) │  Filament web · Parent portal (PWA) · Web Push + FCM v1 · master marks    │
              └───────────────▲───────────────────────────────────────────────▲───────────┘
                              │ idempotent bidirectional sync (deltas)         │
        ┌─────────────────────┴───────────┐                  ┌─────────────────┴───────────┐
        │ SCHOOL NODE A (Lite Local Node) │   …per school…   │ SCHOOL NODE B               │
        │ same image, EDIFIS_MODE=local   │                  │                             │
        │ Laravel+Octane · PostgreSQL ·   │                  │                             │
        │ hostapd Wi-Fi AP + dnsmasq      │                  │                             │
        └──────────┬──────────────────────┘                  └─────────────────────────────┘
        Staff web (https://<school>.local)  +  Flutter app (online → cloud)
```

- **Cloud Brain:** all schools (multi-tenant via stancl/tenancy, DB-per-tenant), parent-facing authority, master academic ledger, notifications, monitoring aggregation.
- **Lite Local Node:** one school; tenancy/parent-portal stripped via `.env`; serves the campus over its own Wi-Fi LAN; survives internet outages.
- **Clients:** (1) **Web** (Filament/Livewire) served by node on campus / cloud off campus — the offline-capable path; (2) **Flutter app** — online-only, all roles, native speed + FCM push; (3) **Parent portal** — cloud-only Livewire PWA.

## 2. Technology stack
| Layer | Choice |
|------|--------|
| Backend | Laravel 11 (PHP 8.3), Octane (Swoole) in Docker |
| DB | **PostgreSQL 16** (ADR-012) — JSONB, strict constraints, MVCC |
| Cache/queue/realtime | Redis, Horizon, Laravel Reverb (WebSockets) |
| Multi-tenancy | stancl/tenancy v3 (cloud), DB-per-tenant |
| AuthN/Z | Laravel Sanctum + PIN/OTP; Spatie Laravel-Permission (8 roles) |
| Admin/web UI | Filament v3 (Livewire/Blade) |
| Docs | DOMPDF (PDF), Laravel Excel (import/export) |
| Push | Web Push (VAPID) + **FCM HTTP v1** (OAuth2 service-account) |
| Mobile | Flutter 3.x (Dart), Riverpod, Dio, go_router, firebase_messaging |
| Infra | Docker Compose; hostapd + dnsmasq on nodes |

## 3. Backend module map (`app/Domain/<Module>`)
Thin controllers/UI → **Action** classes → repositories/models → API Resources. No business logic in controllers/Blade/Livewire.

| Module | Responsibility |
|--------|----------------|
| Tenancy | `ModeGate`, `TenantContext` — resolve school in both modes |
| Auth | tokens, PIN, new-device OTP, trusted devices, revocation, offboarding |
| Students / Consent | enrolment, Master PEA ID, versioned consent |
| Issuance | catalogue import, `IssueItemsToStudent`, returns, signatures |
| Ledger | `PostLedgerDebit`, `BalanceQuery` (derived sum) |
| Attendance | sessions, `RecordScan`, override, void, tally |
| Academics | `RecordMark` (per-record ownership, audited), publish |
| Promotion | `ComputePromotion` (coefficient/baseline/versioned), `OverridePromotion` |
| Audit | append-only `audit_entry`, `AuditLogger` |
| Sync | `ApplyEnvelope` (push/pull/applyPulled), `ConflictResolver`, `CursorService` |
| Timetable | entries (VP authors, Principal approves), calendar |
| Vacuum | `RunQuery` (read-only co-pilot), `RunCommand` (audited), `VacuumGuard` |
| Documents | report cards, receipts, registers, transcripts (DOMPDF) |
| Notifications | typed notifications, Web Push + FCM channels, FCM tokens, push subscriptions |
| Monitoring | node/UPS telemetry intake |

### Shared invariants (enforced in `app/Support`)
`HasUuidV7` (UUID PKs); `AppendOnlyRepository` (only `append()`/`void()` — no update/delete on event tables); `Idempotency::applyOnce` (atomic claim-first inside a transaction); `ClockService` (cloud-authoritative `synced_time`); `AuditLogger`.

## 4. Data model (core, append-only)
See white paper Appendix A and `edifis-contracts/schemas/*`. Key shapes:
- `issue_event` (id, revision, student_id, catalogue_item_id, cost int CFA, issued_at, staff_id, signature_ref, batch_id, status, synced_time)
- `attendance_event` (id, revision, session_id, student_id, scanned_at, source, status, void_reason, synced_time)
- `ledger_entry` (id, student_id, source_event_id, amount int signed, posted_at) — **balance = SUM(amount), never stored**
- `mark` (id, revision, revision_parent, student_id, subject_id, class_id, sequence, owner_teacher_id, score, max_score, coefficient, published, synced_time)
- `consent` (versioned), `student` (Master PEA ID, LWW demographics), `audit_entry` (actor, before/after, time), `mark_conflicts`, `trusted_devices`, `login_otps`, `fcm_tokens`, `push_subscriptions`.

**Money** = integer CFA minor units, never float. **All synced rows** carry `synced_time` (cloud-authoritative watermark).

## 5. Synchronisation design (the hard part)
- **Transport:** `POST /sync` with a `SyncEnvelope` (direction, node_id, cursor, items, priority). Accountability events flush first.
- **Idempotency:** `Idempotency::applyOnce({id,revision})` — atomic `insertOrIgnore` claim inside one DB transaction; replay = no-op; crash = full rollback.
- **Watermark:** the cloud stamps `synced_time` on apply; pull deltas use `synced_time > cursor` (never `created_at`). The node-side runner `php artisan edifis:sync` pushes its `synced_time IS NULL` records (then marks them), pulls deltas + conflicts + revocations, advances the cursor.
- **Conflict resolution per type:** append-only (dedupe by id) · demographics (LWW by authoritative time) · consent (versioned append) · **marks (per-record ownership; cloud-wins only on true divergence, persisted + audited + surfaced to the owning teacher)**.
- **Throttling:** Horizon/Redis queue, per-IP 429 + exponential backoff + jitter; revocation list pulled each cycle.

## 6. Security design
- **TLS:** HTTPS everywhere; campus node uses an internal-CA cert at `https://<school>.local`.
- **At rest:** node disk LUKS-encrypted; trusted-device tokens stored as `sha256(secret)` (cookie holds the random secret); PINs/passwords hashed (bcrypt/argon); OTP codes hashed, short-TTL, attempt-capped.
- **AuthN:** Sanctum tokens (short TTL, offline grace), PIN for parents, new-device email OTP for all users.
- **AuthZ:** Spatie permissions, per-class/subject scoping in policies; VACUUM principal-only; finance never editable via VACUUM.
- **Audit:** every money/mark/VACUUM/override action append-only with actor, device, time, before/after, reason.
- **Privacy:** no student logins; minors' data consent-gated; push payloads non-sensitive; data export/erase designed in (Law 2024/017).

## 7. Frontend design
- **Web (Filament/Livewire):** Resources/Pages call Domain Actions; role-gated; served node+cloud; field workflows (camera QR attendance, signature issuance) are Livewire pages.
- **Flutter app:** `ProviderScope → GoRouter → ConsumerWidget screens → Dio → cloud API`; online-only (no Drift outbox); FCM token registered to the user; parent + staff screens.
- **Parent portal:** cloud-only Livewire PWA (manifest + service worker), PIN/OTP login, per-child dashboard, Web Push.

## 8. Deployment topology
- **Cloud:** Docker Compose (app+Horizon+PostgreSQL+Redis); dedicated-vCPU DB before go-live. Domains `https://<school>.edifis.cm`.
- **Node:** Docker Compose (app+Horizon+PostgreSQL+Redis) on a repurposed desktop acting as Wi-Fi AP (hostapd) + DHCP/DNS (dnsmasq); `restart: always`; UPS-driven safe shutdown. See `INFRA-SCHOOL-SERVER.md`.
- **Lab:** `edifis-infra/lab/` runs 1 cloud + 2 nodes on one desktop with simulated internet outages.

## 9. Quality & testing
Pest (backend) + Livewire tests + Flutter analyze/test. Mandatory: append-only (no update/delete) reflection tests; idempotency/replay tests; derived-balance tests; integer-CFA tests; marks-conflict idempotency+audit; new-device OTP & trusted-device security tests. Backend verified on PostgreSQL 16 in the lab image.
