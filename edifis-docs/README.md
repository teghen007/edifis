# EDIFIS — Documentation

**Educational Data Infrastructure & Foundational Information System**
Multi-tenant, offline-first school-management platform — Presbyterian Education Authority (PEA), Cameroon.

This folder is the complete documentation set for EDIFIS. The white paper is the original design source; the documents below are the formal, implementation-aligned specs.

## Document index

| Doc | What it covers | Read if you are… |
|-----|----------------|------------------|
| [`EDIFIS_FINAL_WHITE_PAPER.md`](EDIFIS_FINAL_WHITE_PAPER.md) | The founding architecture & decisions (source of truth) | anyone — start here for the "why" |
| [`SRS.md`](SRS.md) | **Software Requirements Specification** — scope, roles, functional & non-functional requirements | product owners, PEA, evaluators |
| [`SDD.md`](SDD.md) | **Software Design Document** — architecture, modules, data models, sync, security, tech stack | engineers, reviewers |
| [`INFRA-CLOUD-BRAIN.md`](INFRA-CLOUD-BRAIN.md) | **Cloud Brain infrastructure spec** — hosting, sizing, DB, scaling, backups, domains/TLS | whoever provisions the cloud |
| [`INFRA-SCHOOL-SERVER.md`](INFRA-SCHOOL-SERVER.md) | **School server spec** — the repurposed desktop, network/Wi-Fi, UPS, full per-school requirements + install steps | each school's IT teacher, procurement |
| [`DEPLOYMENT-GUIDE.md`](DEPLOYMENT-GUIDE.md) | **Step-by-step deploy** — Cloud Brain (`edifis.com` + school subdomains), school node on a cheap SIM-router network, HTTPS/`.local`, Flutter app build & distribution | whoever installs it |
| [`ROADMAP-WHATS-LEFT.md`](ROADMAP-WHATS-LEFT.md) | What is built, what remains, and **why each remaining item matters** | planners, sponsors |
| [`API-REFERENCE.md`](API-REFERENCE.md) | **Complete API reference** — every endpoint, auth, request/response shape, error codes. Derived from `edifis-contracts/openapi/` | developers, integrators |
| [`USER-MANUAL-STAFF.md`](USER-MANUAL-STAFF.md) | **Staff user manual** — plain-language how-to per role (Principal, VP, Bursar, Class Master, Subject Teacher, Secretary, Discipline Master) | school staff, trainers |
| [`PARENT-QUICKSTART.md`](PARENT-QUICKSTART.md) | **Parent quickstart** — one-page guide: log in (phone → reversed-phone → PIN), install PWA, enable notifications, what you see | guardians |

## Authoritative engineering records (in the repo root)
| File | Purpose |
|------|---------|
| `/DECISIONS.md` | Architecture Decision Records (ADR-001…019) — the locked decisions these docs implement |
| `/BUILD_PLAN.md` | The phased build plan (Phases 0–12) and task definitions of done |
| `/PROGRESS.md` | The living build ledger (what's done, by whom, with review notes) |

## One-paragraph summary
EDIFIS unifies admissions, fees/boarding ledgers, academic records, attendance, textbook issuance, discipline, and parent communication into one platform engineered for **intermittent internet and unstable power**. The same codebase runs as a central **Cloud Brain** (multi-tenant, all schools) and as a **Lite Local Node** on a repurposed desktop in each school (single-school, survives outages on the campus LAN). Money and accountability data are **append-only immutable events**; the two run modes reconcile by **idempotent bidirectional sync**. Staff use a **web workspace** (served by node on campus, cloud off campus) plus an online **Flutter app**; **parents** use a cloud-only portal/app with push notifications.

*Rule: if any document disagrees with the white paper, the white paper wins — raise it in `/PROGRESS.md`.*
