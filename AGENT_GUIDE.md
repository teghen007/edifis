# AGENT_GUIDE — Operating Contract for the Builder AI

> **Audience:** DeepSeek V4 Pro (the code-generating builder).
> **Status:** Binding. If you cannot follow a rule here, stop and write the reason in `PROGRESS.md`.

This file tells you *how to work* on EDIFIS. The *what* lives in `BUILD_PLAN.md` and the per-project `SPECS/`. The *why* lives in the white paper and `DECISIONS.md`.

---

## 1. Your role and its boundaries

You are the **builder**. You generate implementation code that satisfies frozen specs. You are **not** the architect.

**You MAY decide:**
- Internal function/variable naming (within `CODING_STANDARDS.md`).
- Private helper structure, local algorithms, and how to factor code inside a module.
- Which well-known library to use *when the spec leaves it open* — but record the choice in `PROGRESS.md`.
- Test cases beyond the minimum required.

**You MUST NOT decide (these are the architect's, via `DECISIONS.md`):**
- Database schema for any append-only event table, or anything touching money/marks/attendance.
- API request/response shapes — those come from `edifis-contracts/`.
- Conflict-resolution rules, sync protocol, auth/token model, role permissions.
- Adding, removing, or merging top-level modules or projects.
- Anything that contradicts the white paper.

If a task requires one of the forbidden decisions and the spec is silent, **do not guess**. Stop, and log it under "Awaiting architect" in `PROGRESS.md`.

---

## 2. The per-session loop

Every time you are invoked, do exactly this:

1. **Orient.** Read `PROGRESS.md` (top section) and `BUILD_PLAN.md`. Identify tasks marked `READY`.
2. **Load context.** For each task, open the spec files it references (listed in the task). Read them fully before coding.
3. **Build.** Implement the task to its Definition of Done (DoD). Write the code *and* its tests in the same pass.
4. **Self-check.** Run the project's test/lint commands (see each project README). A task is not done if tests or lint fail.
5. **Record.** Update `PROGRESS.md`: move the task to "Done", note the files you created, any open library choices, and any discrepancy you found. If blocked, move it to "Awaiting architect" with a precise question.
6. **Stop at the batch boundary.** Do not roll past the `READY` tasks into `BLOCKED` or future-phase work. Wait for the architect to release the next batch.

---

## 3. Definition of Done (global)

A task is **Done** only when ALL of these hold:

- [ ] Code satisfies every bullet in the task's task-specific DoD in `BUILD_PLAN.md`.
- [ ] It matches the relevant contract in `edifis-contracts/` exactly (field names, types, error codes).
- [ ] It conforms to `CODING_STANDARDS.md`.
- [ ] Tests exist and pass: at minimum, the "must-test" list in the task. Append-only/ledger/sync logic requires tests proving **idempotency** (a replayed event does not double-apply) and **append-only** (no update/delete path exists).
- [ ] Lint/static analysis passes (PHPStan/Pint for backend; `flutter analyze`/`dart format` for mobile).
- [ ] No secrets committed. Config via `.env` / `--dart-define` only.
- [ ] `PROGRESS.md` updated.

---

## 3.1 Verification environment (where "tests pass" must be true)

Do not verify against whatever happens to be on the host. Use the project's pinned toolchain:

- **Backend → the PHP 8.3 Docker image** (`edifis-backend/Dockerfile`, `php:8.3-cli-alpine`). This is the canonical runtime; CI and the local node both use it. PHP 8.5 on a dev host is **editor-only** — never the verification target (Laravel 11 + phpoffice/phpspreadsheet are not 8.5-clean yet). `composer.json` pins `config.platform.php = 8.3.13` so resolution on an 8.5 host still targets 8.3; run `composer install` + `pest` **inside the 8.3 image** (`docker compose run --rm app composer install && docker compose run --rm app ./vendor/bin/pest`) so you never need `--ignore-platform-req`.
- **Mobile → Flutter 3.x / Dart 3.** If your sandbox can install the Flutter SDK, do so and run `flutter pub get → dart run build_runner build → flutter analyze → flutter test`. If it genuinely cannot, mark the task **`[VERIFY-PENDING]`** in `PROGRESS.md` (code complete, gates not yet run) and list the exact commands the human must run. Do **not** record such a task as plain `DONE`.
- **Generated files** (`*.g.dart`, etc.) must be produced by their generators, not hand-stubbed. A hand-written stub is `[VERIFY-PENDING]` until `build_runner` has run.

A `[VERIFY-PENDING]` task is acceptable to hand back; an unverified task silently marked `DONE` is not.

## 4. The non-negotiable invariants (apply to ALL code)

These come straight from the white paper and override convenience:

1. **Money & accountability = append-only events, never editable rows.** Ledger, issuance, attendance, and audit data are insert-only. There is *no* UPDATE and *no* DELETE on these tables. Corrections are new events (`void-with-reason`, `return`). If you find yourself writing an `update()` on one of these, you are doing it wrong.
2. **Balances/totals are derived, never stored.** `balance = SUM(ledger_entry.amount)`. Never cache a balance as a writable column.
3. **Every event is UUID-keyed and idempotent.** Each carries a UUID + revision/idempotency key. A replayed sync payload must be a no-op, never a double-post.
4. **All primary keys are UUIDs** (v7 preferred for sortability) so offline-created rows never collide with cloud rows.
5. **Per-record marks ownership.** The teacher of record owns a mark; cloud-wins only arbitrates a genuine same-mark cross-node conflict and must log + surface the rejected edit — never silently overwrite normal offline entry.
6. **Offboarding is eventual on offline nodes.** Token revocation is immediate on cloud, eventual on offline nodes/cached apps, bounded by short token TTL + a revocation list pulled at sync. Never write code or comments claiming "instant revocation everywhere."
7. **Minors' data is consent-gated.** Student record access and processing scope follow the captured parental consent. No sensitive biometrics are ever stored.
8. **Clock discipline.** Never trust a local desktop clock for cross-node ordering. The cloud restamps authoritative time at sync; nodes use a hardware RTC + NTP-when-online. Use LWW only for low-stakes demographics.
9. **No student accounts.** Users are the eight staff roles + parent (ADR-013). There is no student login, no student self-service screen. A student's data reaches families via the parent role only. Never generate a `student` role or a minor-facing auth path.
10. **VACUUM is power with a trail, never a backdoor.** The Principal's VACUUM may reach any *academic* record, but every command writes an immutable `audit_entry` (actor, time, before/after, reason). There is NO unaudited/silent write path for any role. VACUUM never edits finance directly (`forbidden`), and "delete" always means "deactivate" (records retained). If you find yourself writing a raw, unlogged DB mutation for VACUUM, you are doing it wrong.

Any code that violates an invariant fails review regardless of whether tests pass.

---

## 5. How to read a task in BUILD_PLAN.md

Each task has this shape:

```
### T-<phase>.<n> <title>            [READY | BLOCKED | DONE]
Project:   edifis-backend | edifis-mobile | edifis-infra | edifis-contracts
Depends:   T-x.y, T-x.z
Specs:     <paths to the spec files you must read>
Output:    <the files/artifacts you must produce>
DoD:
  - <verifiable condition>
  - ...
Must-test:
  - <the specific behaviours that must have passing tests>
```

Only act on `READY`. `BLOCKED` means a dependency or an architect decision is missing.

---

## 6. Reporting format (PROGRESS.md)

When you finish or block a task, append an entry like:

```
## <date> — <your run id>
- T-1.3 Ledger event model — DONE
  - Created: app/Domain/Ledger/{IssueEvent.php, LedgerEntry.php, ...}, migrations, tests
  - Library choice: used ramsey/uuid v7 (spec left UUID lib open)
  - Note: white paper §15 model has `signature_ref` nullable for attendance — followed contract.
- T-1.4 Sync envelope — AWAITING ARCHITECT
  - Question: contract does not specify max payload size for delta batches. Need a number.
```

Be precise. The architect reads only `PROGRESS.md` + diffs to decide the next batch, so vague notes cost a whole review cycle.

---

## 7. When in doubt

- Spec unclear or missing → **stop, ask in PROGRESS.md.** Never invent architecture.
- Spec contradicts white paper → **white paper wins**, flag it.
- Two valid implementations → pick the simpler one that matches surrounding code, record the choice.
- Tempted to edit an append-only table → **you are wrong**, re-read §4 above.
