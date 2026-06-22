# EDIFIS — Architecture Blueprint & 5-Year Roadmap
*Revolutionising school management for Cameroon secondary schools.*
**Author:** Claude (Architect) · **Date:** 2026-06-22 · **Status:** living document

---

## 0. How this was produced
Benchmarked EDIFIS against the three leading open-source school systems and the real
Cameroon secondary report-card model, then mapped the gaps:

| Project | Stack | What we mined |
|---|---|---|
| **UnifiedTransform** (changeweb) | Laravel | `Mark → FinalMark` via **ExamRule weighting**, `GradeRule` bands, session-based **Promotion**, Syllabus, Accounting |
| **Gibbon** (GibbonEdu/core) | PHP | Module breadth: **Markbook, Rubrics, Formal Assessment, Behaviour, Planner, Admissions, Timetable, Reports, Student Alerts, Individual Needs, Library, Finance** |
| **RosarioSIS** (francoisjacquet) | PHP | **Grades/GPA/Honor-roll/Transcripts, Discipline, Eligibility, Scheduling, Student_Billing, Accounting, Food_Service** |
| **Cameroon model** | — | Subject **coefficients**, 6 **sequences**/yr, term+annual averages, **Mention/Appreciation**, **conduct**, **rank**, GCE O/A-Level, bilingual EN/FR |

Sources: [UnifiedTransform](https://github.com/changeweb/Unifiedtransform) · [Gibbon](https://github.com/GibbonEdu/core) · [RosarioSIS](https://github.com/francoisjacquet/rosariosis) · [Cameroon grading](https://www.scholaro.com/db/countries/Cameroon/Grading-System) · [subject coefficients](https://cameroongcerevision.com/subjects-with-weekly-coverage-and-coefficients-for-secondary-schools/subject-coefficients-1/)

---

## 1. What EDIFIS has accomplished (current architecture)

### 1.1 Platform
- **Multi-tenant SaaS** — `stancl/tenancy` (single DB, domain-resolved). Each school = its own subdomain (`pssnkwen.myedifis.com`). The 3 OSS benchmarks are all single-school self-host; **EDIFIS is SaaS-native** — a structural advantage.
- **Backend:** Laravel 11 / PHP 8.3, **Filament 3** admin panel, Sanctum API, Horizon/Redis queues, Postgres 16.
- **Mobile:** Flutter (Riverpod, Dio, go_router), role-aware, offline-tolerant; release APK served from the landing site.
- **Infra:** Docker on a Hostinger VPS, **Caddy** with on-demand TLS per subdomain, code baked into the image (rebuild-to-deploy).

### 1.2 Domain model (academic core)
`Academic Year → Section → Class → Stream → Student`; assessment `Year → Term → Test/Sequence`;
pivots `subject_stream`, `student_stream`, `student_subject`, `teacher_assignments`, `class_masters`.

### 1.3 Capabilities live today
- **Scoped RBAC** — 10 roles; teachers act only on their assigned subject+stream; class masters on their class; bursar on fees.
- **Excel-first pipeline** — download pre-filled template → fill → upload → ingest. Covers **marks** (teacher), **subject enrolment** (class master), **fees** (bursar). This is EDIFIS's pragmatic edge for low-connectivity schools.
- **Results engine** — marks → averages → `grade_rules` bands → `subject_results` + `term_results` (overall average, grade, **class rank**).
- **Report cards** — JSON + **branded PDF** (dompdf).
- **Event-sourced ledger** — `ledger_entries`; balance is derived (`SUM(amount)`), never stored — clean accounting foundation.
- **Attendance** — sessions + QR scan (mobile_scanner), tally.
- **Parent portal** — phone+PIN+OTP auth, children, balances, results, attendance, calendar.
- **Push notifications** — FCM HTTP v1 (fee posted, results published), real service-account OAuth.
- **AI Brain (our moat — none of the 3 OSS have this):**
  - **Parent AI** — scoped strictly to a parent's own children (prompt-injection-resistant).
  - **Principal VACUUM** — natural-language Q&A over a school snapshot.
  - **Auto report-card remarks** — per-student AI comments on compute (queued).
- **Onboarding** — PEA/super-admin approves school requests.

---

## 2. Gap analysis — what to adopt to *polish* (prioritised)

### TIER A — Cameroon-critical (correctness & credibility; do first)
1. **Subject coefficients & weighted averages** *(THE big one).*
   Cameroon report cards weight each subject by a coefficient (Maths 4, etc.).
   EDIFIS currently uses a **simple** average → a Cameroonian principal will see it as *wrong*.
   → add `coefficient` to `subject_stream`; term average = `Σ(subject_avg × coeff) / Σcoeff`; show weighted totals.
2. **Formal sequence→term→annual weighting** (UnifiedTransform `ExamRule` pattern).
   Term avg = mean of its 2 sequences; annual = mean (or weighted) of 3 terms. Make the weighting configurable per school.
3. **Full Cameroon report card** (Gibbon *Reports* + local norms): per-subject **teacher remark**, **coefficient + weighted mark**, **class avg/highest/lowest** per subject, **rank per subject**, **conduct**, **attendance summary**, **Mention/Appreciation** (Excellent/V.Good/Good/Fair/Weak), **form-master + principal remarks**, **promotion decision**.
4. **Conduct & Discipline module** (RosarioSIS *Discipline* + Gibbon *Behaviour*): discipline-master records, conduct grade, sanctions, merits/demerits → feeds the report card.
5. **Promotion / Deliberation engine** (beyond UnifiedTransform's re-enrolment): end-of-year **council decision** (Promoted / Conditional / Repeat / Dismissed) from annual average + rules, recorded and carried into next year.
6. **Bilingual EN/FR** — Cameroon is bilingual; report cards and UI in both.
7. **Real SMS gateway** — many parents use basic phones. Add SMS (local aggregator / Africa's Talking / Twilio) alongside push, reusing the notification layer.

### TIER B — platform maturity (from Gibbon / RosarioSIS)
8. **Timetable engine with conflict detection** (Gibbon *Timetable*) — teacher/room/stream clash checks.
9. **Admissions workflow** (Gibbon *Admissions*) — application → offer → registration → enrolment.
10. **Student Alerts / early-warning** (Gibbon *Student Alerts*) — academic/attendance/behaviour flags → feeds AI dropout-risk.
11. **Finance maturity** (RosarioSIS *Student_Billing* + *Accounting*) — fee structures, invoices, receipts, instalments, **MTN MoMo / Orange Money** integration.
12. **Eligibility** (RosarioSIS) — gate GCE registration on fees paid + attendance.
13. **Lesson Planner / Scheme of work / Syllabus** (Gibbon *Planner* + UnifiedTransform *Syllabus*).
14. **Transcripts / Certificates / GPA / Honor Roll (Tableau d'honneur)** (RosarioSIS *Grades*, Gibbon *Formal Assessment*).
15. **Library, Transport, Boarding/Hostel** modules (boarding is common in Cameroon).

### TIER C — keep extending our moat
- AI: predictive analytics (dropout/performance), student study assistant, automated insights.
- Offline-first sync hardening (the event/ledger backbone already supports it).
- Deeper Excel/round-trip tooling.

---

## 3. Target architecture (final blueprint)

```
┌──────────────────────────── EDIFIS CLOUD (multi-tenant) ────────────────────────────┐
│  Edge: Caddy (auto-TLS per *.myedifis.com)                                           │
│  API: Laravel 11 (Sanctum) ── Admin: Filament 3 ── Jobs: Horizon/Redis               │
│  Data: Postgres 16 (tenant-scoped) · Event/Ledger backbone (append-only)             │
│                                                                                      │
│  DOMAINS                                                                             │
│   Identity & RBAC ·  Academics(Year/Section/Class/Stream/Subject/Coeff)             │
│   Assessment(Sequence→Term→Annual, weighted) · Results(grades/rank/mention)          │
│   Conduct/Discipline · Attendance · Promotion/Deliberation · Admissions              │
│   Finance(Billing/Ledger/MoMo) · Timetable · Planner/Syllabus · Library/Boarding     │
│   Notifications(Push + SMS + Email) · Eligibility · Transcripts/Certificates         │
│                                                                                      │
│  AI BRAIN (provider-agnostic LlmClient)                                              │
│   Parent assistant (scoped) · Principal VACUUM · Auto-remarks ·                       │
│   [roadmap] Dropout-risk · Performance prediction · Student tutor                     │
└──────────────────────────────────────────────────────────────────────────────────────┘
        ▲ REST/Sync                         ▲ Push/SMS
┌───────┴─────────────┐          ┌──────────┴───────────┐
│  Flutter app        │          │  Parents (push+SMS,  │
│  (staff + parent,   │          │  parent portal + AI) │
│  offline-first)     │          └──────────────────────┘
└─────────────────────┘
```

**Design principles:** scoping enforced in code (never in prompts) · balances derived, never stored ·
Excel-first for low connectivity · offline-tolerant clients · AI sees only pre-scoped data ·
every secret in env/mounted, never in the image.

---

## 4. Five-year roadmap — revolutionising Cameroon secondary SMS

### Year 1 (2026-27) — **Correct & credible; first paying schools**
- TIER A in full: **coefficients/weighted averages**, true Cameroon **report card**, **conduct/discipline**, **promotion/deliberation**, **bilingual EN/FR**, **SMS gateway**.
- Billing & **subscriptions** (per-student/term or per-school/year) + **MTN MoMo / Orange Money**.
- Harden go-live: rotate secrets, automated backups + restore drill, audit logging.
- **Goal:** 5–15 anglophone schools live; PSS Nkwen as flagship reference.

### Year 2 (2027-28) — **Scale the SaaS**
- Self-serve onboarding & school provisioning; admissions workflow; timetable engine; eligibility + GCE registration export.
- Parent app maturity (receipts, instalments, SMS+push parity); offline-sync hardening.
- Analytics dashboards (attendance, fees, performance) for principals & proprietors.
- **Goal:** 100+ schools; francophone pilot (full FR report cards).

### Year 3 (2028-29) — **AI-native education**
- Predictive: **dropout-risk** & performance prediction (Student Alerts → AI); automated termly insights.
- **Student study assistant** (scoped, curriculum-aligned).
- Multi-school groups (dioceses, proprietor networks), cross-school benchmarking (privacy-safe).
- **Goal:** 500+ schools; recognised AI differentiator nationally.

### Year 4 (2029-30) — **Ecosystem & payments at scale**
- e-Payments at scale (fees, exam fees, PTA) with reconciliation; receipts/finance-grade accounting.
- Content/e-learning marketplace; teacher CPD; report-card & transcript verification (QR/anti-fraud).
- Integration pathways with **MINESEC**/exam boards (GCE), data exports for policy.
- **Goal:** 1,500+ schools; CEMAC/francophone expansion.

### Year 5 (2030-31) — **The national standard**
- Position EDIFIS as the **de-facto Cameroon SMS standard**; data-driven insights for education policy (aggregate, anonymised).
- Expand scope: primary + vocational + higher-ed modules; pan-African footprint (anglophone + francophone Africa).
- **Goal:** national-scale adoption; sustainable, category-defining platform.

---

## 5. Immediate next actions (the on-ramp to Year 1)
1. **Coefficients & weighted averages** — schema + results-engine change (highest ROI; makes report cards *correct*).
2. **Cameroon report-card v2** — teacher remarks, conduct, class stats, mention, promotion decision (PDF + app + AI remark already in place).
3. **Conduct/Discipline module** (discipline-master role already exists).
4. **SMS gateway** (reuse the notification layer; parents on basic phones).
5. **Security hardening + backups** (go-live gate).
6. **Subscriptions/billing + MoMo** (turn it into revenue).

*Each ships as a tight task to the GLM-5.2 juniors (backend→VPS, mobile→local), reviewed + built by the architect.*
