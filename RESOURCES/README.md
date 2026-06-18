# EDIFIS — Roles & AI Integration

> Extracted from *EDIFIS White Paper v3.0 (Final)* — Presbyterian Education Authority (PEA), Cameroon  
> Source: `EDIFIS_White_Paper_Final.md` in this folder

---

## 1. Role-Based Access Control

EDIFIS uses **Laravel Sanctum tokens** + **Spatie Laravel-Permission** to enforce four user roles via a single Flutter app. Students (minors) have the narrowest access.

### Role Matrix

| Role | Can do | Cannot do |
|------|--------|-----------|
| **Admin/Bursar** | Issue items, log fees & instalments, register students, capture signatures, print receipts/registers | Edit locked academic marks; alter another school's data |
| **Teacher** | Take QR attendance, input sequence marks, record discipline/exeats, print registers for own classes | See students outside their classes; access financial ledgers |
| **Student** (minor) | View own timetable, own results when published, own fee balance, notices | See other students' data; message staff privately; see discipline notes about others |
| **Parent/Guardian** | View each child's balance, results, attendance summary; download receipts and report cards | Edit any record; see unrelated students |

### Authentication & Offline Resilience

- Sanctum token cached in the Flutter app for brief offline login/read access
- Token re-validated when connectivity returns
- No mobile-money payment integration in the initial build (MTN MoMo / Orange Money deferred)

### Staff Offboarding

- Disabling an account **immediately invalidates Sanctum tokens on the Cloud Brain**; offline campus nodes and cached app tokens revoke at the next sync (bounded by a short token TTL + revocation list pulled at sync) — offline-first means revocation is *immediate on cloud, eventual on offline nodes*, never instantaneous everywhere
- Departed user's records retained for audit trail; only access revoked
- Every action remains attributable post-departure (Section 7.3 audit log)

---

## 2. Automated Systems & AI-Adjacent Rules

EDIFIS does not include ML/AI models, but embeds **deterministic rule-based automation** in several subsystems that could feed or be enhanced by AI later.

### 2.1 Automated Student Promotion Engine (Section 5)

| Feature | Detail |
|---------|--------|
| **Coefficient balancing** | Term scores scaled by subject-weighting constants per academic pathway (General vs Commercial/Technical) |
| **Core logic** | Year-long averages evaluated against a configurable baseline (e.g. ≥ 10/20) — advance or repeat |
| **Principal override** | Explicit approval gateway for exceptions (medical cases, etc.) |
| **Full auditability** | Every automated decision + override logged immutably: who, when, old/new outcome, reason |
| **Rule-set versioning** | Rule-set version stored per run so any report card can be reproduced later |

### 2.2 Append-Only Event Ledger (the Integrity Backbone)

The single unifying principle: *anything that touches money or accountability is an immutable event, never an editable row.*

**Data models:**
```
attendance_event: { id (UUID), session_id, student_id, scanned_at, device_id, status, void_reason? }
issue_event:      { id (UUID), student_id, catalogue_item_id, cost, issued_at, staff_id, signature_ref, batch_id, status, reason? }
ledger_entry:     { id (UUID), student_id, source_event_id, amount, posted_at }
```

**Key property:** balance = sum of entries (derived, never stored). Retried syncs cannot double-post.

### 2.3 Automatic Ledger Posting (Section 8.1 Step 4)

- Each textbook/rubber-item issue-event posts its cost to the student's fee/boarding ledger automatically
- Returned books recorded as new "return" events — history is never edited
- Late-arriving offline events merge without conflict (UUID-keyed, append-only)

### 2.4 Conflict-Resolution Rules (Section 4.1)

| Data type | Rule |
|-----------|------|
| Financial & item records | Append-only — never overwritten; both nodes append safely |
| Academic marks | Per-record ownership (teacher of record owns the mark); cloud-wins arbitrates only same-mark cross-node conflicts; rejected local edit logged and surfaced to teacher, never silently overwriting normal offline entry |
| Attendance records | Append-only — only "add" and "void-with-reason" |
| Student profile/demographics | Last-write-wins by NTP-disciplined timestamp |

### 2.5 Synchronisation & Thundering-Herd Mitigation (Section 9)

- **Queueing:** Horizon → Redis queue (not direct DB writes)
- **Jitter:** Randomised sync-delay offsets per node
- **Micro-payload deltas:** Only records changed since last sync
- **Rate limiting + backoff:** HTTP 429 + exponential backoff with jitter
- **Idempotency:** Every record carries UUID + revision key

### 2.6 QR Attendance — Policy-Driven Integrity (Section 8.2)

No biometrics. Attendance integrity enforced by a discipline rule:
- **Fewer scans than students:** present-but-cardless students are added via a default-on, audit-logged teacher override (not silently marked absent, since attendance drives exam eligibility); repeated override use is itself a flag
- **More scans than students:** over-count signals proxy scanning → discipline

Teacher sees live scan count vs own headcount. System only records and shows the tally.

---

## 3. Technology Stack (AI-Ready Foundation)

| Layer | Technology | Role |
|------|-----------|------|
| Backend | Laravel (PHP) + Octane | Core engine, multi-tenant via stancl/tenancy |
| Admin panels | Filament PHP | Staff workspace, Spatie roles |
| Queue/Sync | Horizon + Redis | Background sync, throttling |
| Mobile | Flutter | Unified app for all roles, Sanctum-secured |
| Notifications | Laravel Reverb | WebSocket push (results, payments, exeats) |
| Documents | DOMPDF / Laravel Excel | Report cards, receipts, registers |
| Base | UnifiedTransform (fork) | Pre-built school-management scaffolding |

**Cloud Brain:** Full multi-tenant mode (all schools, parent portal, payment APIs)  
**Lite Local Node:** Single-campus mode (multi-tenancy/parent portal stripped via `.env`)

---

## 4. Future AI Integration Surface

The append-only event ledger and rule-based automation create natural on-ramps for future AI/ML:

- **Predictive analytics** on attendance patterns (QR scan event stream)
- **Financial anomaly detection** (immutable fee/issue ledger)
- **Automated promotion recommendations** (rule engine → ML classifier)
- **Parent engagement insights** (parent portal access + results data)
- **Report-card & document generation** (DOMPDF templates + structured data)

---

*Extracted 2026-06-17 — Full whitepaper at `EDIFIS_White_Paper_Final.md`*
