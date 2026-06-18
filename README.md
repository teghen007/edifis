# EDIFIS — Monorepo Root

**Educational Data Infrastructure & Foundational Information System**
A multi-tenant, offline-first school-management platform for the Presbyterian Education Authority (PEA), Cameroon.

> **If you are an AI code-generation agent (e.g. DeepSeek V4 Pro): START HERE.**
> Read this file fully, then read [`AGENT_GUIDE.md`](AGENT_GUIDE.md) before writing a single line of code. The guide is your operating contract. Do not deviate from it.

---

## 0. What this repository is

This is a **specification-first monorepo**. The architecture, data models, conflict rules, and module boundaries are already decided and frozen in the white paper and the per-project specs. Your job is to **generate the implementation code** that satisfies those specs — not to redesign the system.

The authoritative system description is the white paper:
- [`edifis-docs/EDIFIS_FINAL_WHITE_PAPER.md`](edifis-docs/EDIFIS_FINAL_WHITE_PAPER.md)

Every design decision in this repo traces back to that document. If a spec and the white paper ever disagree, **the white paper wins** and you must flag the discrepancy in `PROGRESS.md` rather than guessing.

---

## 1. Projects (folders from root)

| Folder | Project | Stack | Spec entry point |
|--------|---------|-------|------------------|
| [`edifis-contracts/`](edifis-contracts/) | **Shared contracts** — the single source of truth for API shapes, event schemas, error codes, and the sync protocol. Backend and mobile both implement against this; it prevents drift. | OpenAPI 3.1 + JSON Schema | [`edifis-contracts/README.md`](edifis-contracts/README.md) |
| [`edifis-backend/`](edifis-backend/) | **Cloud Brain + Lite Local Node** — same Laravel codebase, two `.env` modes. Holds all domain logic, the append-only ledgers, sync endpoints, Filament admin, and document generation. | Laravel 11 (PHP 8.3) + Octane + Filament + Horizon/Redis | [`edifis-backend/README.md`](edifis-backend/README.md) |
| [`edifis-mobile/`](edifis-mobile/) | **Unified Flutter app** — one app, four roles (Admin/Bursar, Teacher, Student, Parent), offline-first with a local event queue. | Flutter 3.x + Riverpod + Dio + Drift (SQLite) | [`edifis-mobile/README.md`](edifis-mobile/README.md) |
| [`edifis-infra/`](edifis-infra/) | **Deployment** — Docker Compose for cloud and local node, the desktop-as-WAP config (hostapd/dnsmasq), backups, and monitoring. | Docker Compose, shell, systemd | [`edifis-infra/README.md`](edifis-infra/README.md) |
| [`edifis-docs/`](edifis-docs/) | **Documentation** — the white paper and architecture decision records (ADRs). | Markdown | [`edifis-docs/README.md`](edifis-docs/README.md) |
| [`RESOURCES/`](RESOURCES/) | Source research, sample report-card data, and the original white-paper drafts. **Reference only — do not generate code here.** | — | — |

**Build order (dependency direction):** `edifis-contracts` → `edifis-backend` → `edifis-mobile`, with `edifis-infra` developed alongside the backend. Contracts are generated/agreed first because both apps depend on them.

---

## 2. Governance files (read in this order)

| File | Purpose |
|------|---------|
| [`AGENT_GUIDE.md`](AGENT_GUIDE.md) | **Your operating contract.** How to work, what to do each session, what you may and may not decide, definition of done, how to report. |
| [`DECISIONS.md`](DECISIONS.md) | Locked architectural decisions (ADRs). These are **not** open for re-litigation. |
| [`CODING_STANDARDS.md`](CODING_STANDARDS.md) | Conventions for PHP/Laravel and Dart/Flutter: naming, structure, testing, commits. |
| [`BUILD_PLAN.md`](BUILD_PLAN.md) | The full phased task list (Phase 0 → N). Each task has an ID, dependencies, and a definition of done. |
| [`PROGRESS.md`](PROGRESS.md) | The living ledger. You **must** update this after every task: mark done, note blockers, record any spec/white-paper discrepancies. |

---

## 3. The working loop (human + two AIs)

This project is built by **DeepSeek V4 Pro** (bulk code generation) under periodic **architectural review by Claude/Anthropic** (scan, verify, dictate next batch). The split exists to control cost.

```
        ┌─────────────────────────────────────────────────────┐
        │  CLAUDE (architect, periodic, expensive)            │
        │  - full scan of repo + PROGRESS.md + diffs          │
        │  - verifies specs were followed, catches drift      │
        │  - writes the next batch of tasks into BUILD_PLAN   │
        └───────────────────────┬─────────────────────────────┘
                                │ dictates next tasks
                                ▼
        ┌─────────────────────────────────────────────────────┐
        │  DEEPSEEK V4 PRO (builder, continuous, cheap)       │
        │  - reads BUILD_PLAN + the relevant SPECS            │
        │  - generates code to the definition of done         │
        │  - updates PROGRESS.md, flags blockers              │
        └─────────────────────────────────────────────────────┘
```

**DeepSeek:** work only on tasks marked `READY` in `BUILD_PLAN.md`. When you finish a batch or hit something the specs don't cover, stop and write it in `PROGRESS.md` under "Awaiting architect" — do not invent architecture.

---

## 4. Quick status

- **Current phase:** Phase 0 — Foundations (see `BUILD_PLAN.md`).
- **What exists now:** the full scaffold, specs, contracts skeleton, and config boilerplate. No business logic yet.
- **Next:** see the `READY` tasks at the top of `BUILD_PLAN.md`.
