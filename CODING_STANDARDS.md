# CODING_STANDARDS

Conventions every generated file must follow. Kept short and enforceable. Lint config in each project enforces most of this automatically.

---

## 1. Universal

- **Language of code & comments:** English. Domain terms match the white paper (e.g. `issue_event`, `ledger_entry`, `Master PEA ID`).
- **No secrets in code.** Config via `.env` (backend) or `--dart-define` (mobile). Commit `.env.example`, never `.env`.
- **No dead code, no commented-out blocks.** If it's not used, don't generate it.
- **Every public function has a one-line doc comment** stating intent, not restating the signature.
- **Fail loud, not silent.** Validation rejects bad data with a typed error from the contract's error model; never import-or-skip silently.
- **UUIDs:** v7 for any entity that is created offline and synced.

## 2. Backend — PHP 8.3 / Laravel 11

- **Style:** PSR-12, enforced by **Laravel Pint**. Static analysis by **PHPStan (level 6+)**.
- **Architecture:** domain-oriented. Business logic lives in `app/Domain/<Module>/`, *not* in controllers. Controllers are thin: validate (FormRequest) → call a domain Action/Service → return an API Resource.
- **Naming:** Classes `PascalCase`; methods `camelCase`; DB tables `snake_case` plural; columns `snake_case`. Event tables end in `_event` (e.g. `attendance_event`, `issue_event`).
- **Actions pattern:** one invokable class per use case, e.g. `App\Domain\Issuance\Actions\IssueItemsToStudent`. Keep them pure and testable.
- **Append-only repositories** expose only `append()` and `void(reason)` — no `update`/`delete`. Enforce with a base `AppendOnlyRepository` and a test asserting the methods don't exist.
- **Migrations:** one concern per migration; never edit a shipped migration — add a new one. Event tables get no `updated_at`.
- **API responses:** JSON:API-ish via Laravel API Resources; shapes must match `edifis-contracts/openapi`.
- **Tests:** Pest (preferred) or PHPUnit. Feature tests hit routes; Unit tests cover domain Actions. Money/sync logic must include idempotency + append-only tests.

## 3. Mobile — Dart / Flutter 3.x

- **Style:** `dart format` + `flutter analyze` clean (lints from `flutter_lints` + `very_good_analysis`).
- **State:** Riverpod (`@riverpod` codegen). No `setState` for anything beyond trivial local widget state.
- **Structure:** feature-first under `lib/features/<feature>/` with `data/`, `domain/`, `presentation/` inside each. Shared primitives in `lib/core/` and `lib/shared/`.
- **Networking:** Dio client in `lib/core/network/`. Generated DTOs mirror `edifis-contracts/schemas` exactly (use `json_serializable`).
- **Local store:** Drift (SQLite). The **outbox** table holds pending events; a sync service drains it. Reads come from Drift, never block on the network.
- **Naming:** Classes `PascalCase`; files `snake_case.dart`; providers end in `Provider`.
- **No business logic in widgets.** Widgets read providers; providers call repositories; repositories own the outbox + API.
- **Tests:** widget tests for screens, unit tests for repositories/sync. The outbox replay path must have a test proving no double-post.

## 4. Git / commits (when a repo is initialised)

- Conventional Commits: `feat(ledger): ...`, `fix(sync): ...`, `test(attendance): ...`, `docs: ...`, `chore(infra): ...`.
- One task (T-x.y) per branch where practical; reference the task ID in the commit body.
- Never commit generated framework caches, `vendor/`, `node_modules/`, build artifacts, or `.env`.

## 5. Definition-of-done reminder

Code is not done until tests + lint pass and `PROGRESS.md` is updated. See `AGENT_GUIDE.md` §3.
