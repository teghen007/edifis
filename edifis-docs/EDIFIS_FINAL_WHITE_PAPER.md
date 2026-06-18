# EDIFIS — Final White Paper

**Educational Data Infrastructure & Foundational Information System**
A Multi-Tenant, Offline-First School Management Platform

**Presbyterian Education Authority (PEA) — Cameroon**
Initial deployment: 4 secondary schools · Designed to scale to 25+
Version 3.1 (Final, Consolidated) · June 2026

---

## Table of Contents

1. Executive Summary
2. System Overview
3. Technology Stack
4. Local Campus Server (Linux Desktop as WAP + LAN)
5. Data Authority, Synchronization & Conflict Resolution
6. Automated Student Promotion Engine
7. Unified Mobile App & Role-Based Access
8. Student Identity, Consent & Audit Trail
9. Item Issuance & Attendance
10. Network Throttling (Thundering-Herd Mitigation)
11. Operational Risks & Mitigations
12. Programme-Level Assessment
13. Capacity & Hardware Sizing
14. Compliance & Go-Live Readiness
15. Appendix A — Data Models
16. Appendix B — Change Log (v3.0 → v3.1)

---

## 1. Executive Summary

EDIFIS is a centralized, multi-tenant school management platform for the Presbyterian Education Authority's secondary schools in Cameroon. It is engineered for an environment of intermittent internet (MTN / Camtel) and unstable mains power (ENEO), and therefore runs **offline-first**: each campus operates a self-contained local node that synchronizes with a central cloud whenever connectivity allows.

The platform unifies what are normally separate systems — admissions, fee and boarding ledgers, academic records, discipline, attendance, textbook issuance, and parent communication — into one workspace, with a Flutter mobile app serving the staff hierarchy and parents under role-based access (students are minors and have no accounts; see §7).

This white paper consolidates the full architecture and, in Section 9, defines the item-issuance and attendance subsystem around three field-tested decisions: QR-code attendance on student ID cards, per-item textbook issuance with a fast batch-signature flow, and an append-only event ledger that makes money and accountability records tamper-evident and sync-safe.

The guiding engineering principle throughout: **anything that touches money or accountability is recorded as an immutable event, never an editable row.** This single decision underpins financial integrity, audit defensibility, and conflict-free synchronization.

This final edition also fixes the platform's scope and obligations: mobile-money integration (MTN MoMo / Orange Money) is explicitly **deferred** and out of the initial build; parental-consent capture, student photos, and a full audit log are confirmed as **must-have** features; and a capacity-sizing section and a go-live readiness checklist are included so deployment is planned rather than improvised.

This v3.1 consolidation additionally corrects four claims that the offline-first design could not literally deliver — token revocation timing, marks-conflict authority, local durability between syncs, and clock discipline without connectivity — and adds an explicit account-provisioning plan. These corrections are summarized in Appendix B.

---

## 2. System Overview

EDIFIS serves an initial cohort of **4 schools**, with the architecture designed to scale to **25 or more**. The same codebase runs in two modes, switched by environment configuration:

- **Cloud Brain:** Full multi-tenant mode managing all schools, the parent portal, and third-party payment APIs. Holds the master academic ledger and the authoritative parent-facing data.
- **Lite Local Node:** A single campus's instance with multi-tenancy, parent dashboards, and cloud integrations stripped out via `.env`. Processes only that school's data, fast on modest hardware.

Synchronization avoids whole-database mirroring; nodes exchange only the records changed since their last successful sync, flagged by localized timestamps.

### 2.1 Initial Operations Model

Rather than a 25-school big-bang, EDIFIS launches with 4 schools. The **system-administrator role is held by each school's IT teacher**, who is responsible for keeping the local server online and accountable for its operation. Node health and UPS status are reported to a central monitoring endpoint, so failures (a dead disk, a node offline, a UPS on battery) are visible centrally rather than discovered when a bursar calls.

This phased, monitored rollout is a deliberate risk-reduction choice — it lets the hardest subsystem, bidirectional sync, prove itself on a small fleet before wider deployment.

Mobile-money payment integration (MTN MoMo / Orange Money) is **deferred** and is not part of the initial build. Fee and item ledgers are maintained by staff entry; online payment can be added later on top of the same append-only ledger without rework.

---

## 3. Technology Stack

Built on the PHP / Laravel ecosystem for backend stability and rapid UI development, with a Flutter client for mobile users.

| Layer | Technology | Role |
|-------|-----------|------|
| Backend | Laravel (PHP) | Application logic and routing; `stancl/tenancy` for multi-tenant partitioning. |
| Staff workspace | Filament PHP | Admin panels for bursars and administrators; Spatie Laravel-Permission for roles. |
| Base scaffolding | UnifiedTransform (fork) | Pre-built school-management modules used as the starting codebase. |
| Local performance | Laravel Octane | Keeps the app resident in memory via Docker for speed on old hardware. |
| Queue & sync | Horizon + Redis | Background sync jobs and throttling with `laravel-offline-sync`. |
| Mobile | Flutter | Single app for the staff hierarchy + parents (no student accounts, §7); secured by Laravel Sanctum. |
| Real-time | Laravel Reverb | WebSocket push notifications (exeats, payments, results). |
| Documents | DOMPDF / Laravel Excel | Report cards, fee receipts, printed attendance registers; rubric-sheet import/export. |

- **Cloud Brain:** Full multi-tenant mode (all schools, parent portal, payment APIs).
- **Lite Local Node:** Single-campus mode (multi-tenancy/parent portal stripped via `.env`).

---

## 4. Local Campus Server (Linux Desktop as WAP + LAN)

Each campus runs its Lite Local Node on a repurposed legacy desktop PC running Linux (Ubuntu Server / Debian). The desktop itself provides the local network: it acts as a wireless access point and wired LAN gateway, so teachers and staff connect to it directly even when the wider internet is down.

- **Wireless AP:** `hostapd` broadcasts a campus SSID (e.g. `EDIFIS-<SchoolCode>`).
- **Addressing & local name:** `dnsmasq` provides DHCP and resolves a friendly hostname so staff reach the system on campus at e.g. `https://pssnkwen.local` (served with an internal CA certificate, per the hardening note below).
- **Remote access:** From outside campus Wi-Fi, the same users reach the Cloud Brain at e.g. `https://pssnkwen.edifis.cm`. The app detects which path is reachable.
- **Containerized stack:** Docker Compose runs Laravel + Octane + Redis + the tenant database, listening on the local subnet.

```
ON CAMPUS                                   OFF CAMPUS
   https://pssnkwen.local                      https://pssnkwen.edifis.cm
   (internal CA cert)
        │                                              │
   ┌────┴───────────────────────┐                ┌─────┴───────┐
   │ Repurposed Linux Desktop   │   sync (when   │   Cloud     │
   │ Docker: Laravel+Octane+    │◄──internet────►│   Brain     │
   │ Redis + Tenant DB          │   returns)     │ (25 tenants)│
   │ hostapd (AP) + dnsmasq     │                └─────────────┘
   └────┬──────────────┬────────┘
    Wi-Fi          Ethernet
   teacher        bursar/admin
   tablets        desktops
```

> **⚠ ENGINEERING NOTE — Hardening for the desktop-as-WAP node.**
> Confirm the Wi-Fi adapter supports AP/master mode before bulk purchase (e.g. Atheros `ath9k_htc`, MediaTek `mt7601u`). For whole-campus reach, treat the desktop as the gateway and add dedicated access points wired to a switch rather than relying on one radio. Secure the SSID with WPA2/WPA3, isolate the subnet, serve the app over HTTPS with an internal certificate, and run full-disk encryption (LUKS) so a stolen machine yields no readable student or financial data.

---

## 5. Data Authority, Synchronization & Conflict Resolution

Teachers off-campus route to the Cloud Engine; on-campus they use the local node. Authority over each record type is split, and all keys are UUIDs so rows created offline never collide with cloud rows.

- **Academic authority — Cloud, with per-record ownership:** The cloud holds the primary ledger for marks and report cards; local nodes pull modifications sequentially. "Cloud authoritative" governs genuine *cross-node* conflicts, not ordinary entry: each mark is owned by the teacher of record for that class/subject, and that teacher's offline entry on the campus node is the source of truth for that record until a different actor edits the same mark elsewhere. Cloud-wins therefore arbitrates only when two nodes edit the *same* mark — it must never silently overwrite a teacher's normal offline entry simply because the cloud synced later.
- **Financial authority — Campus:** The campus node is the master for physical fee and item entries; changes queue locally and push up when connectivity returns.

### 5.1 The Conflict-Resolution Rule

UUIDs prevent identifier collisions but not edit collisions (two nodes changing the same record). EDIFIS resolves this **per data type** rather than with one global rule:

| Data type | Rule on conflict |
|-----------|------------------|
| **Financial & item records** (fees, book issues) | Append-only events — never overwritten. A book issue is a new immutable ledger line; balances are derived sums. Two nodes can both append safely. |
| **Academic marks** | Per-record ownership, cloud arbitrates true conflicts. Each mark is owned by the teacher of record; their offline entry stands as authoritative for that mark. Only when two nodes edit the *same* mark does cloud-wins apply — and even then the rejected local edit is logged and surfaced to the teacher, never silently dropped. Cloud-wins is a conflict tie-breaker, not a licence to overwrite normal offline entry. |
| **Attendance records** | Append-only events. Only "add" and "void-with-reason" — never edit. |
| **Student profile / demographics** | Last-write-wins by timestamp, with clock discipline that accounts for offline operation. NTP needs connectivity the campus does not always have, so each node carries a battery-backed hardware RTC and disciplines it via NTP whenever a link is up; the cloud restamps authoritative time at sync to fix cross-node ordering. LWW is used only for low-stakes demographic fields where losing one side's edit is acceptable — it is deliberately *not* used for marks, money, or any accountability record. |

> **✔ DESIGN DECISION — One principle solves three problems.**
> Anything that affects money or accountability is an append-only event log, never a mutable row. This single decision removes the hardest sync conflicts, prevents double-posting on retried syncs, and produces a tamper-evident audit trail — the same property used for the issuance log in Section 9.

---

## 6. Automated Student Promotion Engine

End-of-year transitions are computed automatically with configurable rules, with a human override path.

- **Coefficient balancing:** Sequence/term scores are scaled by subject-weighting constants per academic pathway (General vs Commercial/Technical).
- **Core logic:** Year-long averages across sequences are evaluated; students at or above the configurable baseline (e.g. ≥ 10/20) advance, others repeat.
- **Override:** Principals can override outcomes through an explicit approval gateway (e.g. medical cases).
- **Auditability:** Every automated decision and every override is logged immutably (who, when, old/new outcome, reason), and the **rule-set version is stored per run** so any report card can be reproduced later.

---

## 7. Unified Mobile App & Role-Based Access

A single Flutter app serves the school's staff hierarchy plus parents, with Laravel Sanctum tokens and Spatie roles deciding what each sees. **Students do not have accounts.** They are minors who are not expected to carry phones; their data lives in the system (marks, fees, attendance, consent) but they never log in. Their QR ID card is a physical card only. This deliberately removes every minor login from the platform, strengthening the data-protection posture under Cameroon Law No. 2024/017 (Section 14.1).

The app users are therefore **staff** (the institutional hierarchy below) and **parents/guardians** (adults, who receive read access to their own children's records).

For low-connectivity use, the app caches a valid Sanctum token and the user's last-synced data so login and read access work briefly offline, re-validating when a connection returns. (See Section 14.6 for how this interacts with offboarding and token revocation.)

| Role | Can do | Cannot do |
|------|--------|-----------|
| **Principal** (institution super-user) | Everything academic within the school: view all data; correct any mark; approve/override promotions; deactivate accounts; run the VACUUM command mode + AI co-pilot (Section 7.1). Every action is logged. | Cannot silently edit (all changes are audited); cannot directly rewrite the append-only financial ledger; cannot touch another school's data. |
| **Vice Principal (VP)** | Deputy oversight of studies and discipline; build/approve the master timetable and calendar (Section 7.2); view school-wide academic and attendance data; record discipline. | Financial ledger edits; cross-school access; VACUUM (Principal-only). |
| **Bursar / Administrative staff** | Issue items, log fees & instalments, register students, capture signatures, print receipts and registers. | Edit locked academic marks; alter another school's data. |
| **Class Master / Mistress** | Own one class end-to-end: take QR attendance, coordinate that class's marks, record discipline/exeats, print that class's registers. | See classes other than their own; access financial ledgers. |
| **Subject Teacher** | Enter sequence marks for their own subjects; take QR attendance for classes they teach; print their own registers. | See students/marks outside their subjects/classes; access financial ledgers. |
| **Discipline Master (SDM)** | Record discipline cases and exeats; view attendance/discipline across classes. | Edit marks; access financial ledgers. |
| **Secretary / Admin Clerk** | Enrolment, demographic records, document printing. | Edit marks or financial ledgers; approve promotions. |
| **Parent / Guardian** | View each child's balance, results, attendance summary; download receipts and report cards. | Edit any record; see unrelated students. (Online payment deferred to a later phase.) |

### 7.1 VACUUM — the Principal's Command Mode & AI Co-pilot

The Principal is the super-user of the institution and is given a dedicated, powerful command surface called **VACUUM**. It has two faces:

- **AI co-pilot (read & analyse):** The Principal queries the school in plain language — "show every student failing Mathematics in Form 4", "who is borderline for promotion", "which classes are below 80% attendance", "compare this term's averages to last term" — and the assistant scans all academic data and answers instantly. This is a natural-language window over the same data the reports expose; it reads, it does not invent.
- **Command authority (act, with a trail):** From the same surface, the Principal can act on the whole institution's academic record — correct any mark in any class, force-promote or repeat any student, override the promotion engine, and deactivate any account — and the AI can carry out these commands on instruction. Bulk and destructive actions require an explicit confirmation step.

**The one rule that makes VACUUM safe:** it grants *unlimited reach, never invisibility.* Every VACUUM action is written as an immutable, attributable event — who (the Principal), when, old → new value, and the reason — exactly like every other accountability record in the system (Section 8.3). The Principal can change anything academic; the system simply remembers that he did. This is protection *for* the Principal: a corrected grade reads as a deliberate, reasoned override by a named authority, not as an unexplained change that later looks like tampering.

**Two hard boundaries on VACUUM, by design:**
1. **Finance is never directly editable, even by VACUUM.** The append-only fee/issue ledger is corrected only through proper return/adjustment events (Section 9), never by rewriting a balance. The reason marks are now editable-but-audited and money is append-only-via-events is the *same* reason — both are the records people most try to game — so both stay tamper-evident.
2. **"Delete" means "deactivate."** Accounts and records are deactivated, not erased: a departed user's authored history is retained for the audit trail (Section 14.6) and Cameroon's record-keeping rules. Nothing that ever happened is silently removed.

VACUUM is a per-institution capability gated to the Principal role and can be enabled or disabled per school. Its entire action stream is itself auditable.

### 7.2 Timetables & Calendar of Activities

Two scheduling artefacts are first-class:

- **Master timetable** (class periods — which teacher takes which subject, which class, when) is built by the **VP (Studies)** or a designated timetable officer and **approved by the Principal**. Teachers see their own periods; a Class Master sees their class's full timetable; parents see their child's.
- **Calendar of activities** (term dates, sequence/exam windows, holidays, PTA meetings, sports, feast days) is maintained by the **Principal / VP / Secretary** and is visible school-wide.

Both feed the home screens and notifications (Laravel Reverb), so "what's next" is always current even when the wider internet is down (served from the local node).

### 7.3 Frontend Strategy — Web-first, with a minimal offline app and a cloud parent portal

EDIFIS presents through three surfaces, each matched to its job:

- **Web platform (offline-capable path):** A server-rendered Filament + Livewire app inside the same backend, served by **both** the local node and the cloud. On campus the browser reaches the node (`https://<school>.local`) and keeps working through an internet outage because the LAN is up; off campus it reaches the cloud. It carries **all** staff work — enrolment, fees/issuance, marks, documents, timetable, the Principal's VACUUM page, **and the field workflows (QR attendance via the browser camera, classroom issuance with an on-screen signature pad).** Because attendance is a short (~20-minute) activity, an internet outage simply means taking it in the browser against the local server.
- **Flutter native app (online, all roles):** A thin native app (Android/iOS/desktop) that talks to the cloud, valued for **speed and reliable native push notifications (FCM)** — especially for parents, who prefer a fast app. It is **online-only by design**: it carries no offline buffer, because the offline path is owned by the node + browser. If the internet is down, the user falls back to the browser on the local server.
- **Parent access:** Parents are cloud-only and choose either the **Flutter app** (native FCM push) or a small mobile-first **web portal** (installable PWA, Web Push) — both deliver notifications when results publish, fees post, or attendance/exeat/calendar events occur (SMS deferred, not in the pilot). Login is by phone number with a PIN (bootstrapped from the reversed phone number, reset on first use); a one-time email code verifies any new device. Guardians see only their own children and never connect to a node.

The same dual-mode backend serving both node and cloud (no fork) is what lets the web platform survive a campus blackout; the Flutter app adds native convenience and push on top, without taking on offline responsibility.

---

## 8. Student Identity, Consent & Audit Trail

Three features are treated as mandatory rather than optional, because they underpin both legal compliance and day-to-day integrity.

### 8.1 Parental / Guardian Consent at Enrolment

Because EDIFIS processes the personal data of minors, parental or guardian consent is captured as part of enrolment and stored against the student record. The system records **who consented, their relationship to the student, the date, and the scope of processing agreed to.** Consent is versioned: if processing purposes change, a fresh consent is recorded rather than overwriting the old one, so the school can always show what was agreed and when. This directly supports Cameroon's data-protection requirements for processing children's data (see Section 14).

### 8.2 Student Photographs

A photograph is captured for each student at enrolment and stored against the record. It serves three purposes: it is printed on the QR student ID card (making cards harder to swap), it gives teachers a visual register to cross-check against attendance scans (reinforcing the anti-proxy model in Section 9), and it appears on report cards and official documents. Photographs of minors are treated as personal data under the same consent and access rules as the rest of the student record.

### 8.3 Audit Trail

Every change to a mark or a financial/ledger entry is attributable to a specific user, with a timestamp and the before/after values. The audit log is **append-only and cannot be edited, only read.** This serves two ends: it satisfies the law's record-keeping expectations, and — in an environment where records are sometimes contested or gamed — it makes every change traceable, which is as much a deterrent as a forensic tool. Combined with the append-only ledgers elsewhere in the design, no figure in the system is unexplained: each traces back to an actor, a device, and a moment in time.

> **✔ DESIGN DECISION — Consent, photo and audit are first-class, not add-ons.**
> These are built into the enrolment and editing workflows from day one. Retrofitting consent records or backfilling 4,000 student photos after launch is far more painful than capturing them at the point of enrolment, and the audit log only has value if it has been running since the first record was created.

---

## 9. Item Issuance & Attendance

This section is built around how distribution and roll-call actually happen in these schools, and around a clear position on biometrics. It covers three workflows: textbook / rubric-item issuance, QR-code attendance, and the integrity model that makes both trustworthy without heavy enforcement software.

> **✔ DESIGN DECISION — Biometrics are dropped; signature + QR replace them.**
> Earlier drafts proposed fingerprint biometrics for students. EDIFIS does not use student biometrics. Storing fingerprints of minors carries real legal and security weight, and the simpler mechanisms below achieve the same accountability:
> - Item issuance is acknowledged by a signature on the tablet/desktop — enough to settle a billing dispute, like signing for a delivery.
> - Attendance uses the QR code on each student's ID card. The integrity comes from a school policy, not from biometric hardware.

### 9.1 Textbook & Rubric-Item Issuance

Distribution is realistic: the bursar carries a pile of books into a classroom and issues several items — sometimes ten or more — to each student. Each item must be tracked individually so the school can later prove a specific student received a specific book and never returned it. But issuing ten items with ten signatures per student would never survive a real distribution day. EDIFIS resolves this tension with a **default-rubric-plus-batch-signature** flow.

**Step 1 — Upload the rubric.** Before distribution, the bursar uploads a sheet (Excel) listing every rubric item and its cost — textbooks, uniforms, lab fees, boarding items. Items are grouped into a default set per form/class (e.g. Form 1 receives these eight titles). Importing the sheet creates the catalogue that drives automatic ledger postings.

**Step 2 — Issue by exception.** In the classroom, the bursar selects a student (or scans the student's QR ID to pull them up). The app pre-loads that form's default rubric with every item checked. The bursar simply unchecks anything the student did not actually receive. This makes the common case — a student getting the full set — a single tap, while still allowing per-student variation.

**Step 3 — One signature, many records.** The running total is shown (e.g. "10 items — 45,000 CFA"). The student signs once on the tablet/desktop signature pad. Behind that single signature, the system writes **one immutable issue-event per item**, each linked to the catalogue entry, its cost, the timestamp, the staff member, and the captured signature image. Per-book accountability, no per-book friction. (The single signature legally acknowledges the batch total, in the same way a delivery signature covers the whole consignment.)

**Step 4 — Automatic ledger posting.** Each issue-event posts its cost to the student's fee/boarding ledger as an append-only debit. The balance is the derived sum of events, so a retried sync can never double-charge, and a returned book is recorded as a new "return" event rather than by editing history.

```
+---------------------------------------------------------------+
|              BURSAR ISSUANCE  -  TANGU NEBA (Form 1)          |
+---------------------------------------------------------------+
|  [x] Mathematics Bk 1 .............. 8,000  (default Form 1)  |
|  [x] Integrated Science ........... 12,000                   |
|  [x] English Course Bk ............. 7,500                   |
|  [ ] French Grammar ................ 6,000  (not given)      |
|  [x] PE Uniform ................... 11,500                   |
|              ...  6 more items checked  ...                  |
+---------------------------------------------------------------+
|  TOTAL: 10 items   -   45,000 CFA                            |
|             [  STUDENT SIGNS ONCE BELOW  ]                   |
+---------------------------------------------------------------+
|   [ CONFIRM -> writes 10 ledger events + debits balance ]    |
+---------------------------------------------------------------+
```

> **⚠ ENGINEERING NOTE — Make book selection fast with barcode/ISBN scanning (optional).**
> If textbooks carry barcodes, the bursar's tablet camera can scan each book's ISBN to confirm the exact copy issued, which strengthens "you received this specific book." Where books are not barcoded, the default-rubric checklist is the fallback. Either way, each issued item remains an individual record.

### 9.2 QR-Code Attendance

Every student carries an ID card printed with a unique QR code. A teacher opens a class session in the app and scans students in with the tablet/phone camera. Each scan is recorded against that session as it happens, and the session can be printed as a register.

**The session is the unit of record.** Attendance is recorded against a class session — class, subject/period, and date/time — not merely a date, so the same class can have separate morning, afternoon, or per-subject roll calls. Each scan is an append-only event tied to one session; nothing is edited, only added or voided with a reason.

**How a roll call runs.**
1. The teacher opens a session for their class (e.g. 4A — English — Tue AM).
2. Students present hold up their ID cards; the teacher scans each QR. The app shows a live running count and the list of names marked present.
3. The teacher closes the session. The scan total becomes the record.
4. The register can be printed for the class file or for an inspector, and per-student term summaries (e.g. 58/60 sessions) are produced from the same event records.

**The integrity model: policy, not enforcement software.** A QR card proves the card is present, not that the student is. EDIFIS handles this with a school discipline rule rather than software policing, which is simpler and fits the context:

- **Fewer scans than students present:** If the teacher sees 40 students but only 38 scan, the two without cards are not auto-recorded. Because attendance feeds consequential decisions (exam eligibility, term summaries), a present-but-cardless student must **not** be silently marked absent: the teacher override is **enabled by default**, letting the teacher add a present student manually as an audit-logged add-with-reason event (so the override is auditable and proxy abuse stays detectable). It remains the student's responsibility to carry their ID — repeated reliance on the override is itself a flag — but the default protects a genuinely present student from an unjust absence.
- **More scans than students present:** If 42 cards are scanned but only 40 students are in the room, two extra cards are being scanned for absent friends. This over-count is the signal: the teacher identifies whose cards those are, and those students are disciplined for proxy attendance.

The system's only jobs are to record each scan, show the running total against the teacher's own headcount, and let the teacher close the session. Integrity comes from the rule — your card is your responsibility and proxy-scanning is a punishable offence — which the visible count makes enforceable without any biometric or photo-verification machinery.

```
+---------------------------------------------------------------+
|   ATTENDANCE  -  4A / ENGLISH / Tue 16 Jun AM                 |
+---------------------------------------------------------------+
|   Scanned: 38      Teacher headcount: [ 40 ]                  |
|   ---------------------------------------------------------   |
|   38 present (scanned)                                        |
|   2 present but cardless -> add via default-on override (log) |
|   ! If scanned > headcount: extra cards = proxy, investigate  |
+---------------------------------------------------------------+
|        [ CLOSE SESSION ]      [ PRINT REGISTER ]             |
+---------------------------------------------------------------+
```

> **⚠ ENGINEERING NOTE — Handle the practical edge cases in policy.**
> Lost/forgotten card: a manual teacher override (enabled by default, logged as an auditable add-with-reason event) lets the teacher record a genuinely present student so they are not unjustly marked absent; the override is the default path, not an optional extra, because attendance drives consequential decisions. Damaged cards are reprinted by admin against the same student QR identity. Poor lighting/dust: the camera scan tolerates this far better than fingerprint readers would, which is part of why QR was chosen over biometrics for the classroom.

### 9.3 Offline Behaviour & Data Model

When the classroom is within campus Wi-Fi, scans and issue-events hit the local node live. When the bursar or teacher is out of range, the tablet queues events locally and syncs them when it rejoins the network — because every event is append-only and UUID-keyed, late-arriving events merge without conflict and cannot double-post.

The shared data models for both subsystems are given in Appendix A. Because balances and attendance totals are computed from immutable events, the system is auditable end to end: every figure traces back to a specific scan or signed issue, with the actor, device, and time attached.

---

## 10. Network Throttling (Thundering-Herd Mitigation)

When regional internet returns, many schools may sync at once. EDIFIS spreads the load:

- **Queueing:** Horizon places payloads in a Redis queue instead of hitting the database instantly.
- **Jitter:** Nodes use randomized sync-delay offsets so they do not all reconnect simultaneously.
- **Micro-payload deltas:** Only records changed since the last sync are sent.
- **Rate limiting + backoff:** The server enforces per-IP limits (HTTP 429); nodes retry with exponential backoff and jitter. Every record carries an **idempotency key** (UUID + revision) so a replayed payload is never applied twice.
- **Priority lane for accountability data:** Financial, issuance, and attendance events are flushed first when any link comes up, minimizing the window in which committed money/accountability data exists only on a single node (see Section 11).

---

## 11. Operational Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Local server theft or disk death** | Encrypted (LUKS) disk so theft yields no readable data; automated tested backups plus push of financial events to the cloud as a read-only shadow whenever connectivity allows. **Honest limit:** events created since the last successful sync exist only on that node, so a disk death during an offline window can lose the un-synced tail. This is mitigated, not eliminated, by (a) syncing financial/attendance events opportunistically and as a priority queue the moment any link is up, (b) an append-only on-disk journal flushed on write, and (c) a mirrored second SSD on the node so a single disk failure is survivable locally. The shadow copy guarantees durability only up to the last sync — it is not a substitute for local redundancy. |
| **Power instability (ENEO)** | UPS triggers safe shutdown; BIOS set to power-on after AC loss; Docker `restart: always` revives the stack unattended. UPS state reported to central monitoring. |
| **Student identity collision across schools** | Lifetime Master PEA ID (e.g. `PEA-2026-00124`) issued by the cloud; academic and financial history follow it on transfer. |
| **Data fragmentation (ghost classes)** | Staff select class and rubric names from cloud-controlled dropdowns; no free-typing, so reports align across schools. |
| **Proxy attendance / fake names** | QR-on-ID plus the count-reconciliation policy in §9.2; over-counts expose proxy scanning, disciplined by the school. Student photos give teachers a visual cross-check. |
| **Sync edit conflicts** | Per-type rules in §5.1; append-only events for everything financial or accountability-related. |
| **Stale access after offboarding on an offline node** | Short Sanctum token TTL + revocation list pulled at every sync, so a disabled account loses access at next sync or token expiry even on a node that was offline at the moment of disablement (see §14.6). |

---

## 12. Programme-Level Assessment

Beyond component risks, three realities determine whether EDIFIS succeeds in production. They are recorded here deliberately, because they are easy to underestimate during fast development.

- **Scope is the main risk, not code.** Forking a base system while adding multi-tenancy, offline bidirectional sync, a mobile app, attendance, and issuance is a large surface. The 4-school phased launch is the correct mitigation: prove sync and the core ledger on a small fleet before scaling. Deferring mobile-money to a later phase deliberately narrows the initial scope.
- **Operations beats features in the field.** Dust, heat, power, and a server unplugged to charge a phone cause more outages than bugs. The IT-teacher administrator role, central node/UPS telemetry, and remote-management access are therefore first-class parts of the design, not afterthoughts.
- **Mobile-money reconciliation is a deferred subsystem.** MoMo and Orange Money are out of the initial build. When added later, their webhooks fail, double-fire, and time out, so the integration will need an idempotent ledger and a daily reconciliation job, not just a webhook handler. The append-only ledger in this paper is deliberately the foundation that makes that future reconciliation safe — no rework required.

---

## 13. Capacity & Hardware Sizing

Sizing is driven by the actual load of the initial deployment: roughly **4,000 students plus about 300 staff across 4 schools** (~1,000 accounts per school). This is a light workload by database standards — a few hundred concurrent users at peak, performing form submissions and reads, with a total dataset in the low single-digit gigabytes. The system is not CPU- or RAM-bound under normal use; the real sizing drivers are the **post-results read burst** in the parent app and the **sync "thundering-herd" spike** when connectivity returns.

### 13.1 Cloud Brain

The whole dataset fits comfortably in memory, so the priority is keeping the database on **dedicated (not shared) vCPU** before go-live, because sync bursts are exactly the moment shared-CPU "noisy neighbour" contention hurts.

| Component | Recommended | Notes |
|-----------|-------------|-------|
| App + web tier | 4 vCPU / 8–16 GB RAM | Handles the parent-app read burst and API traffic with headroom. |
| Database | Dedicated vCPU, 16 GB RAM (own instance, or co-located if budget-tight) | RAM keeps the working set cached; dedicated cores avoid sync-burst contention. |
| Redis + queues + Reverb | Shares the app tier at this scale | Sync queue and websocket notifications. |
| Storage | 80–160 GB NVMe SSD | Dataset is small; space is for backups, report-card PDFs, signature & photo images, logs. |
| Bandwidth | Generous (20 TB-class included on most providers) | Far more than 4 schools will use. |

Indicative cost on a value provider such as Hetzner is roughly **€30–65/month** for all four schools combined (e.g. a dedicated-vCPU CCX-class instance for the app, with the database either on its own instance or co-located initially and split out as load grows). The platform scales vertically with minimal downtime, so it is safe to start modest and grow.

> **⚠ ENGINEERING NOTE — The one non-negotiable cloud rule.**
> Put the database on dedicated vCPU before go-live, even if the app tier stays on shared vCPU. The database is where contention causes visible slowness during sync bursts and result-day spikes; everything else can scale up later without drama.

### 13.2 Local Campus Server (Repurposed Desktop)

Each Lite Local Node serves a single school with multi-tenancy and the parent portal stripped out — genuinely light, and well within reach of a repurposed desktop, provided two upgrades are made.

| Component | Minimum | Recommended | Why |
|-----------|---------|-------------|-----|
| CPU | Dual-core x86-64 | Quad-core (2015+ i5/Ryzen) | Octane keeps the app resident; per-request load is low. Don't chase CPU. |
| RAM | 8 GB | 16 GB | The real constraint — Docker + Octane + Redis + DB + cache. 4 GB thrashes; 16 GB is comfortable and cheap to add. |
| Storage | 256 GB SSD | 480–512 GB SSD (mirrored pair preferred) | SSD is non-negotiable — the single biggest speed win on old hardware. A mirrored second SSD makes a single disk failure survivable locally (see §11). |
| Network | Gigabit Ethernet + AP-mode Wi-Fi adapter | + dedicated APs wired to a switch | One desktop radio ≈ one wing; add APs for full-campus coverage. |
| Power | UPS (required) | UPS sized for safe-shutdown + brief ride-through | Drives the ENEO blackout-handling logic. |
| Clock | System clock | Battery-backed hardware RTC | Disciplines timestamps during long offline windows when NTP is unreachable (see §5.1). |

**Scale-up triggers:** split the database onto its own dedicated instance once the app tier shows sustained pressure; add a load balancer and a second app instance as schools pass roughly 10; and revisit the single-IT-teacher administration model before 25 schools, since that is a known operational scaling cliff rather than a technical one.

> **⚠ ENGINEERING NOTE — Two cheap upgrades and one spare.**
> For each repurposed desktop, max the RAM to 16 GB and fit an SSD — together they turn a sluggish office PC into something that runs a single school comfortably. Separately, keep one pre-imaged cold-spare desktop on hand: a single node is a single point of failure for a campus's live operations, and a swap-and-resync is cheaper and faster than any redundancy scheme. The cloud holds the shadow copy and academic master, so a dead node loses no committed data up to the last sync.

---

## 14. Compliance & Go-Live Readiness

### 14.1 Data-Protection Compliance (Cameroon)

Cameroon enacted **Law No. 2024/017** on personal data protection in December 2024, with a transition window for aligning data processing to its requirements. EDIFIS processes the personal data of thousands of minors, which the law scrutinizes most closely. The features already built into EDIFIS address the core obligations:

- **Parental/guardian consent:** Captured at enrolment with relationship, date, and scope (Section 8.1), matching the requirement that processing a minor's data rests on verified parental or guardian consent.
- **Records of processing:** The catalogue of what data is held and why is maintained, supporting the law's record-keeping and impact-assessment expectations.
- **Data-subject rights:** Export and deletion of a student's data are designed in, so access, correction, and erasure requests can be honoured.
- **Sensitive data avoided:** Dropping fingerprint biometrics removes the need for prior authorisation to process a minor's sensitive data — a deliberate simplification, not just a security choice.

This is a design-level summary, not legal advice; the specific obligations, the supervisory Authority's registration or authorisation steps, and the current compliance deadline should be confirmed with a lawyer familiar with the Cameroonian framework before go-live.

### 14.2 Data Migration Plan

Importing ~4,000 existing students cleanly is the first real test of the system and is routinely underestimated. The plan:

1. Define a canonical import template (Excel) per record type: students, classes, prior marks, outstanding balances, guardians.
2. Build a validating import pipeline that rejects or flags duplicates, missing required fields, and malformed data rather than importing silently.
3. Run a dry-run import per school into a staging environment; have each school's staff verify a sample against their paper records.
4. Freeze source data, run the final import, reconcile counts and balances, then go live for that school.

### 14.3 Account Provisioning & Credential Bootstrapping

Onboarding ~4,000 students, their guardians, and ~300 staff is where school rollouts most often stall, so credential creation is planned rather than improvised:

- **Staff** accounts are created by the school administrator and activated via a one-time link or temporary password forced to reset on first login; roles (Spatie) are assigned at creation.
- **Student** accounts are generated in bulk from the migration import (Section 14.2) against the Master PEA ID; for minors, the account is provisioned but its scope follows the captured parental consent (Section 8.1).
- **Guardian** access — the hardest case, because many guardians are on feature phones — is bootstrapped at enrolment by binding a guardian phone number to each child and issuing a short **claim code** (printed on the enrolment slip / report card) that the guardian redeems once to set a credential. Where smartphone access is absent, the parent-facing read data (balance, results, attendance summary) is also deliverable via the existing Reverb/notification and printed-report channels, so no guardian is locked out by device.
- **No shared or default passwords survive go-live:** every bootstrap path forces a reset, and unredeemed claim codes expire.

### 14.4 Backup & Restore Runbook

A backup that has never been restored is not a backup. Required:

- **Automated daily encrypted backups:** Both cloud (database + uploaded files) and local node, retained on a schedule with off-box copies.
- **Rehearsed restore drill:** A documented, timed procedure to rebuild from backup — run before go-live, not during the first incident.
- **Node-failure runbook:** The exact steps to swap in the cold-spare desktop, restore its data, and resync with the cloud, written down and tested once per school.

### 14.5 Required Document Outputs

Adoption depends on EDIFIS producing the paperwork staff already rely on, in the exact expected format. These must be mapped and validated against real samples before go-live:

- **Report cards:** Per term/sequence, in the school's existing layout, bilingual where required, reflecting the Cameroon grading and coefficient structure.
- **Mark sheets & broadsheets:** Class-wide records teachers and principals expect for end-of-term processing.
- **Transcripts & attestations:** Cumulative academic records and official letters, following a student via the Master PEA ID on transfer.
- **Fee receipts & attendance registers:** Printable from the append-only ledgers and attendance sessions described earlier.

### 14.6 Staff Offboarding

When a staff member leaves or is dismissed, access must be revoked completely — with an honest account of what "immediately" can mean in an offline-first system:

- **Revocation, and its offline limit:** Disabling the account invalidates the user's Sanctum tokens on the Cloud Brain immediately. Because EDIFIS is offline-first, revocation reaches an offline campus node — and a Flutter app holding a cached token for offline use (Section 7) — only at the next successful sync, not instantaneously. The gap is bounded deliberately: cached tokens carry a short time-to-live and must re-validate against the cloud when connectivity returns, and each node pulls a revocation list at sync, so a disabled account loses cloud access at once and campus/app access at the next sync or token expiry, whichever comes first. "Immediate everywhere" is not claimed, because an offline node cannot be told anything until it reconnects.
- **Reassignment, not deletion:** The departing user's records (marks entered, items issued) are retained for the audit trail; only their access is removed, and their responsibilities are reassigned.
- **Audit continuity:** Because every action is attributable (Section 8.3), a departed user's history remains intact and accountable after they leave.

---

## 15. Appendix A — Data Models

Both the attendance and issuance subsystems share one shape: immutable, UUID-keyed events from which all totals are derived.

```
attendance_event: {
  id (UUID),
  session_id,
  student_id,
  scanned_at,
  device_id,
  status (present | void),
  void_reason?            // for default-on present-but-cardless overrides and corrections
}

issue_event: {
  id (UUID),
  student_id,
  catalogue_item_id,
  cost,
  issued_at,
  staff_id,
  signature_ref,         // one signature image may back a batch of issue_events
  batch_id,
  status (issued | returned | void),
  reason?
}

ledger_entry (derived from issue_event): {
  id (UUID),
  student_id,
  source_event_id,
  amount,
  posted_at
}
// balance = SUM(ledger_entry.amount) — derived, never stored
```

**Key properties:**
- Balances and attendance totals are derived sums of immutable events — never stored, never edited.
- Retried syncs cannot double-post: every event carries a UUID + revision idempotency key.
- Corrections are new events (`return`, `void-with-reason`), never edits to history.

---

## 16. Appendix B — Change Log (v3.0 → v3.1)

This consolidation corrects claims the offline-first architecture could not literally deliver, and fills two planning gaps. No design principle changed; the corrections make the document match what the system can actually guarantee.

| # | Area | v3.0 claim | v3.1 correction |
|---|------|-----------|-----------------|
| 1 | **Offboarding** (§14.6) | "Disabling an account immediately invalidates all Sanctum tokens across all nodes." | Immediate on cloud; **eventual on offline nodes/cached apps**, bounded by short token TTL + revocation list pulled at sync. |
| 2 | **Marks authority** (§5) | "Cloud is authoritative; on conflict cloud wins." | **Per-record ownership** — teacher of record owns the mark; cloud-wins arbitrates only true same-mark cross-node conflicts, never silently overwriting normal offline entry. |
| 3 | **Durability** (§11) | "Near-real-time shadow… so the master ledger survives loss." | Shadow guarantees durability **only up to the last sync**; un-synced tail mitigated by priority sync, on-disk journal, and a mirrored second SSD. |
| 4 | **Clock discipline** (§5.1) | "Last-write-wins by NTP-disciplined timestamp." | NTP needs connectivity the campus lacks; added **battery-backed RTC**, cloud restamp at sync, and restricted LWW to low-stakes demographics only. |
| 5 | **Transport** (§4) | On-campus URL shown as `http://`. | Corrected to `https://` with an internal CA certificate, consistent with the hardening note. |
| 6 | **Account provisioning** (§14.3) | *(absent)* | Added a full credential-bootstrapping plan for staff, bulk students, and feature-phone guardians; no default passwords survive go-live. |
| 7 | **Attendance fairness** (§9.2) | "No card = student's loss," override optional. | Teacher override is **default-on and audit-logged**, so a present-but-cardless student is not unjustly marked absent; over-count proxy detection unchanged. |

---

*End of White Paper — EDIFIS v3.1 (Final, Consolidated) · June 2026*
