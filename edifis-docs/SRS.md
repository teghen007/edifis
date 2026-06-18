# EDIFIS — Software Requirements Specification (SRS)

Version 1.0 · aligned to build Phases 0–12 · PEA, Cameroon.
Conventions: **MUST** = mandatory; **SHOULD** = recommended; **MAY** = optional. IDs like `FR-x` (functional), `NFR-x` (non-functional).

---

## 1. Purpose & scope
EDIFIS is a school-management platform for the Presbyterian Education Authority's secondary schools. It digitises admissions, fees & boarding ledgers, academic records, attendance, textbook/rubric issuance, discipline, promotion, document production, and parent communication. Initial deployment: **4 schools**, designed to scale to **25+**.

**In scope (pilot):** everything above, delivered via a web workspace + an online mobile app + a parent portal, across a Cloud Brain and per-school local nodes.
**Out of scope (deferred):** mobile-money payments (MTN MoMo / Orange Money), SMS notifications, predictive analytics/ML, iOS app build. See `ROADMAP-WHATS-LEFT.md`.

## 2. Users / actors (roles)
No student accounts — students are minors and do not log in (ADR-013). Actors:

| Actor | Description |
|-------|-------------|
| **Principal** | Institution super-user; approvals, overrides, and the **VACUUM** command mode + AI co-pilot. |
| **Vice Principal (VP)** | Studies/discipline oversight; builds the timetable & calendar (Principal approves). |
| **Bursar / Admin staff** | Fees, item issuance, receipts, student registration. |
| **Class Master / Mistress** | Owns one class: attendance, that class's marks, discipline. |
| **Subject Teacher** | Marks for own subjects; attendance for taught classes. |
| **Discipline Master (SDM)** | Discipline cases, exeats. |
| **Secretary / Admin Clerk** | Enrolment, records, document printing. |
| **Parent / Guardian** | Read-only view of own children + notifications (cloud-only). |
| **IT Teacher (system admin)** | Keeps the school node online; not a data role. |

## 3. Functional requirements

### 3.1 Identity, auth, access
- **FR-1** The system MUST authenticate staff by credential and enforce the 8-role matrix via role-based permissions (Spatie).
- **FR-2** The system MUST scope data by role: a class_master/subject_teacher sees only their classes/subjects; finance is hidden from non-bursar; parents see only their own children.
- **FR-3** Parents MUST log in by phone number; the bootstrap credential is the reversed phone number, forced to reset to a **PIN** on first login (ADR-019).
- **FR-4** All users MUST pass a **new-device email OTP** check; a verified device is trusted ~90 days via a signed cookie. (Web cannot read IMEI; device identity is a signed token.)
- **FR-5** Disabling an account MUST revoke access immediately on the cloud and at next sync on offline nodes (token TTL + revocation list). The user's authored records are retained (deactivate, never delete).

### 3.2 Students, consent, identity
- **FR-6** The system MUST enrol a student with a lifetime **Master PEA ID** and capture **versioned parental/guardian consent** (who, relationship, date, scope). Consent is required for a minor.
- **FR-7** A student photo MAY be captured for the QR ID card, visual register, and documents; treated as minors' personal data.

### 3.3 Fees, issuance, ledger (append-only)
- **FR-8** The bursar MUST import a rubric catalogue (Excel) with default item sets per form.
- **FR-9** Issuance MUST support default-rubric-plus-exception: pre-checked items, uncheck what's not received, one signature, **one immutable issue-event per item**.
- **FR-10** Each issue-event MUST auto-post a ledger debit; a balance is the **derived sum** of ledger entries (never a stored, editable field).
- **FR-11** A return MUST be a new event (credit), never an edit; the original event is immutable.
- **FR-12** Issuance MUST capture and store a real signature image referenced by the events.

### 3.4 Attendance
- **FR-13** Attendance MUST be recorded against a **class session** (class + subject/period + datetime), as append-only scan events; idempotent per session+student.
- **FR-14** The system MUST support QR scanning (device camera) with manual entry fallback, a live scanned-count vs teacher headcount, and a **default-on, audited override** for a present-but-cardless student (reason required).
- **FR-15** Corrections MUST be void-with-reason events; totals/registers derive from events.

### 3.5 Academics, promotion, documents
- **FR-16** Marks MUST use **per-record teacher ownership**; every change is audited (before/after); students/parents see a mark only when published.
- **FR-17** The promotion engine MUST compute coefficient-weighted averages against a configurable, **versioned** baseline; the Principal MAY override via an audited, append-only override.
- **FR-18** The system MUST produce report cards, mark sheets/broadsheets, transcripts, fee receipts, and attendance registers (PDF), in the school's format.

### 3.6 Timetable & calendar
- **FR-19** The VP MUST author the master timetable; the Principal approves; reads are role-scoped. The calendar of activities is school-wide.

### 3.7 VACUUM (Principal)
- **FR-20** VACUUM MUST provide a read-only natural-language co-pilot over academic data and an **audited command mode** (correct mark, promote/repeat, override, deactivate). Every command writes an immutable audit entry with reason. Finance is never directly editable; "delete" means deactivate.

### 3.8 Synchronisation
- **FR-21** Node and cloud MUST sync bidirectionally, exchanging only deltas since the last cursor, **idempotently** (replay = no-op), with append-only/marks-ownership/LWW-demographics/consent-versioned conflict rules; mark conflicts are surfaced to the owning teacher, never silently dropped.

### 3.9 Notifications
- **FR-22** The system MUST notify guardians on results-published, fee-posted, attendance-flagged, exeat, and calendar events via **Web Push** (portal) and **FCM** (app) + an in-portal feed. (SMS deferred.) Push payloads MUST be non-sensitive.

### 3.10 Monitoring & operations
- **FR-23** Each node MUST report health/UPS/disk/last-sync/pending-outbox telemetry to a central endpoint.
- **FR-24** Automated, tested backups and a documented restore drill MUST exist for cloud and node.

## 4. Non-functional requirements
- **NFR-1 Offline-first:** the campus node MUST remain fully usable for staff on the LAN during an internet outage.
- **NFR-2 Power resilience:** the node MUST shut down safely on UPS signal and auto-restart its stack after power returns.
- **NFR-3 Integrity/auditability:** money/marks/attendance/audit data MUST be append-only and tamper-evident; every figure traces to an actor, device, and time.
- **NFR-4 Security:** TLS everywhere (internal CA on campus); full-disk encryption (LUKS) on nodes; OWASP-grade auth; least-privilege roles; no minor logins; trusted-device tokens stored hashed.
- **NFR-5 Privacy/compliance:** align to Cameroon Law No. 2024/017 (minors' data, consent, data-subject rights, no sensitive biometrics).
- **NFR-6 Performance:** support a few hundred concurrent users per school at peak; result-day read burst and sync "thundering-herd" are the sizing drivers.
- **NFR-7 Capacity:** ~4,000 students + ~300 staff across 4 schools; low single-digit GB dataset.
- **NFR-8 Portability:** one codebase runs cloud or node by env (`EDIFIS_MODE`); no fork.
- **NFR-9 Maintainability:** domain logic in `Domain/*` Actions; no business logic in UI; ≥ test coverage on money/sync invariants.
- **NFR-10 Localisation:** English now; bilingual-ready document outputs.

## 5. Constraints & assumptions
- Intermittent internet (MTN/Camtel) and unstable mains (ENEO) are the norm.
- Field devices are Android tablets / desktops; iOS deferred (needs a Mac to build).
- Each school provides one repurposed desktop + UPS + an AP-capable Wi-Fi adapter (see `INFRA-SCHOOL-SERVER.md`).
- Parents may have only feature phones; the portal must degrade to printed reports and (later) SMS.

## 6. Acceptance (pilot go-live)
Data-migration dry-run reconciled per school; backups restored in a drill; document formats validated against real samples; node health telemetry live; staff trained (incl. PIN/OTP and node uptime). See `ROADMAP-WHATS-LEFT.md` for the gating items.
