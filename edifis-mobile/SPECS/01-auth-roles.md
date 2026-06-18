# Mobile SPEC 01 — Auth, Token Cache, Offline Read, Role Routing (Phase 1)

Contract: `/auth/login`, `/auth/revocations`, `_error`. White-paper §6, §7; ADR-006.

## Login + token cache
- Login screen → `POST /auth/login` → store `{token, expires_at, role, user_id}` in secure storage + `sync_state`.
- A valid cached token lets the user log in and read offline within the grace window; re-validate on reconnect.
- On `token_expired`/`token_revoked`, drop to login; if offline and within grace, allow read-only with a banner.

## Revocation awareness
- `SyncService` pulls `/auth/revocations` each sync; if the current token/user is listed → force logout. (Eventual on offline nodes, by design — no claim of instant revocation.)

## Role routing
- After login, `go_router` redirects to the role shell; guards enforce the §7 matrix. Provide placeholder screens now; features fill them in later phases.

## Must-test
- Cached token enables offline read within grace; expired token → login; a revoked-then-pulled token forces logout; role router renders the correct shell per role and blocks cross-role routes.

## Outputs
```
lib/features/auth/{data/auth_repository.dart, domain/*, presentation/{login_screen.dart, auth_providers.dart}}
lib/shared/role_router.dart
lib/core/auth/{token_store.dart, auth_interceptor.dart}
test/auth_router_test.dart
```
