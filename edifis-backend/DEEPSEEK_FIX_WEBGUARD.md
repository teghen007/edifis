# DeepSeek — Fix Filament login: add a `web` session guard (THE root cause)

> VPS task (backend). Filament login fails with "credentials do not match" for valid users because
> `config/auth.php` has `'defaults' => ['guard' => 'sanctum']` and **no `web` (session) guard** — only
> the token-based `sanctum` guard exists, which can't do stateful `Auth::attempt()`. Add a `web`
> session guard and make the Filament panel use it. (The API keeps using `auth:sanctum` — unaffected.)
> Verified: password is correct (API: secret→200, wrong→401) and canAccessPanel passes — the ONLY
> problem is the missing session guard.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD: `... up -d --build app horizon`; then `php artisan config:clear && php artisan filament:optimize`
> - END: `git add -A && git commit -m "fix(auth): add web session guard for Filament login" && git push`
> - Delete this file; report "web guard fix done" + confirm admin reaches the dashboard.

## Edit 1 — add the `web` guard in `config/auth.php`
In the `'guards'` array (which currently only has `sanctum`), ADD a `web` session guard:
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'sanctum' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```
Leave `'defaults' => ['guard' => 'sanctum', ...]` as-is (the API depends on it).

## Edit 2 — make the Filament panel use the `web` guard
In `app/Providers/Filament/StaffPanelProvider.php`, add `->authGuard('web')` to the `panel()` chain
(e.g. right after `->login()`):
```php
->login()
->authGuard('web')
```

## Verify
```bash
# session guard now exists + login page ok
curl -s -o /dev/null -w 'login: %{http_code}\n' https://pssnkwen.myedifis.com/staff/login
```
Then in a browser (incognito) log into `https://pssnkwen.myedifis.com/staff`:
- **admin@pssnkwen.local / secret** → reaches the admin **dashboard** with the Academic/Classes/People/Assignments nav.
- **bih.patience@pssnkwen.local / secret** (principal) → also reaches the dashboard.
Report "web guard fix done" + confirm both logins succeed.
