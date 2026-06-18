# EDIFIS — Roadmap: What's Built, What's Left, and Why

A frank status of the platform and the work between "code-complete" and "schools depending on it." Each remaining item says **why it matters** so priorities are clear.

---

## 1. What is built and verified ✅
- **Backend** (Cloud Brain + Lite Local Node, one codebase) on **PostgreSQL 16** — append-only ledgers, atomic idempotent sync, per-type conflict resolution, marks ownership, VACUUM (audited), promotion engine, documents, monitoring. **94 backend tests green.**
- **Staff web** (Filament/Livewire) — enrolment/consent, fees/issuance, marks, promotion, timetable, documents, VACUUM page; served node + cloud.
- **Web field workflows** — camera-QR attendance + classroom issuance with **real stored signatures**.
- **Parent portal** (cloud PWA) — phone+PIN login, **new-device email OTP**, per-child dashboard.
- **Notifications** — Web Push (portal) + **FCM HTTP v1** (app) + in-portal feed.
- **Flutter app** — online-only (no offline outbox), all roles, FCM token registration; `flutter analyze` clean, tests green.
- **Local test lab** — 1 cloud + 2 nodes on one desktop with simulated internet outages.

## 2. What's left before pilot go-live (config/ops, not code)
These are gating — do them per the infra docs.

| Item | Why it must be done | Where |
|------|---------------------|-------|
| **Firebase project + service-account + `google-services.json`** | Push won't actually deliver to the app without it; FCM code is ready but needs the project/keys | `INFRA-CLOUD-BRAIN.md §5`, `edifis-mobile/SETUP-FLUTTER.md §5` |
| **Build + field-test the APK** on a real Android tablet against the cloud/lab | Proves the native app end-to-end (login, reads, push) before staff rely on it | `SETUP-FLUTTER.md §4` |
| **Production domains + TLS** (cloud subdomains; node internal-CA cert) | Security (NFR-4) and tenant routing; staff/parents need trusted HTTPS | `INFRA-CLOUD-BRAIN.md §4`, `INFRA-SCHOOL-SERVER.md §3` |
| **Per-school node provisioning** (desktop, Wi-Fi AP, UPS, LUKS, install) | The offline path; node uptime is load-bearing | `INFRA-SCHOOL-SERVER.md` |
| **Data migration** (≈4,000 students) with validating import + dry-run reconcile | The first real test; silent bad imports corrupt everything downstream | SRS §6; backend SPEC 05 |
| **DOMPDF templates validated against real report cards** | Adoption depends on producing the exact paperwork staff expect | backend SPEC 04 (T-4.3/T-8.4) |
| **Backup + rehearsed restore drill** (cloud + node) | An untested backup is not a backup; this protects irreplaceable records | both infra docs |
| **Monitoring/alerts live** | Field failures (dead disk, node offline, UPS on battery) must be seen centrally | `INFRA-CLOUD-BRAIN.md §7` |
| **Staff training** (PIN/OTP, node uptime, issuance/attendance flows) | "Operations beats features in the field" — the platform fails if people can't run it | SRS §6 |

## 3. Deliberately deferred (designed-for, add later) — and why
| Item | Why deferred | Why it should be added later |
|------|--------------|------------------------------|
| **SMS notifications** | Costs money; needs a Cameroon aggregator; pilot uses Web/FCM push | Many guardians have only feature phones — SMS is the universal reach; the channel interface is already stubbed |
| **Mobile-money (MTN MoMo / Orange Money)** | A reconciliation subsystem of its own (webhooks fail/double-fire/time out) | Removes cash handling; the append-only ledger is already built to make reconciliation safe with no rework |
| **iOS app** | Requires a Mac + Xcode + Apple Developer account | If iPhones are in scope for staff/parents; same Flutter codebase builds it on a Mac |
| **Parent online payments** | Depends on mobile-money | Convenience + fewer cash trips once MoMo lands |
| **Predictive analytics / anomaly detection / ML promotion aid** | Not needed for core operations | The immutable event ledger is a natural feeding ground (attendance/finance/marks) once there's data history |

## 4. Known technical debt (non-blocking, fix when convenient)
- **Sync push-tracking writes `synced_time` onto append-only event rows** to mark them pushed. It's benign (metadata, not content; the repo-level no-update guarantee holds), but ideally pushed-state lives in a **separate sync-tracking table** to keep event rows strictly insert-only. (`/PROGRESS.md` update 18.)
- **`flutter doctor`** flags Visual Studio (Windows desktop) and CocoaPods (iOS) — irrelevant for the Android pilot; ignore until those targets are in scope.

## 5. Hardening recommended before wide rollout (beyond pilot)
- Point-in-time recovery (WAL archiving) on the cloud DB.
- Load balancer + second app instance past ~10 schools; split DB to its own instance under sustained pressure.
- Revisit the **single-IT-teacher** node-admin model before 25 schools (an operational scaling cliff, not a technical one).
- Legal review of the Cameroon Law No. 2024/017 obligations (registration/authorisation, retention, data-subject workflows) with a local lawyer before go-live.
- Formal security review / pen test of the auth surface (PIN/OTP/trusted-device, sync endpoints, VACUUM).

## 6. Suggested sequencing
1. **Stand up the cloud** (domains, TLS, DB, push keys, backups, monitoring).
2. **Provision one pilot school node** end-to-end; run the lab-style offline→sync test for real.
3. **Migrate that school's data** (dry-run → reconcile → freeze → import).
4. **Validate document outputs** against its real report cards.
5. **Train staff**, soft-launch with that one school, watch monitoring.
6. **Roll to the remaining 3** schools; then SMS + mobile-money as the next phase.

---

*The code is done across backend, web, parent, push, and the app. What remains is the disciplined, unglamorous deployment work that decides whether software that demos becomes software that survives a blackout in Nkwen.*
