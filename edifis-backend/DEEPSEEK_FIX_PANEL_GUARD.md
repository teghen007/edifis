# DeepSeek — Fix Filament panel access (roles on sanctum guard vs web guard)

> VPS task (backend). NO ONE can log into `/staff` ("These credentials do not match our records") even
> with a valid password, because spatie roles are stored on the **`sanctum`** guard (used by the API),
> but Filament authenticates on the **`web`** guard. Spatie's `hasRole`/`hasAnyRole` are guard-scoped →
> they return false in the Filament/web context → `canAccessPanel()` and the resource role-gates fail.
> Confirmed: `admin@pssnkwen.local`/`secret` returns 200 via the API but is rejected by Filament.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD: `... up -d --build app horizon`; then `php artisan filament:optimize`
> - END: `git add -A && git commit -m "fix(admin): guard-agnostic role checks for Filament panel" && git push`
> - Delete this file; report "panel guard fix done" + confirm admin login reaches the dashboard.

## Fix — make Filament-side role checks guard-agnostic
1. On the `User` model add a helper that checks roles **regardless of guard** (direct relation query):
```php
public function hasAnyRoleName(array $names): bool
{
    return $this->roles()->whereIn('name', $names)->exists();
}
```
2. In `User::canAccessPanel()`, replace the `hasAnyRole([...])` call with the new helper:
```php
public function canAccessPanel(\Filament\Panel $panel): bool
{
    return $this->active && $this->hasAnyRoleName([
        'principal','vice_principal','bursar','class_master',
        'subject_teacher','discipline_master','secretary','school_admin',
    ]);
}
```
3. **Every Filament Resource** that gates visibility on a role (look for `hasAnyRole(`, `hasRole(`,
   `->hasRole`, `shouldRegisterNavigation`, `canViewAny` referencing roles in `app/Filament/Resources/**`):
   change those role checks to use `auth()->user()?->hasAnyRoleName([...])` so they work on the web guard.
   (Grep: `grep -rn "hasAnyRole\|hasRole" app/Filament` to find them all.)

> Do NOT change the API `role:` middleware or how roles are assigned (the API uses the sanctum guard and
> already works). This only fixes the WEB/Filament side.

## Verify
- `curl -s -o /dev/null -w '%{http_code}\n' https://pssnkwen.myedifis.com/staff/login` → 200.
- Log into `https://pssnkwen.myedifis.com/staff` as **admin@pssnkwen.local / secret** → reaches the admin
  **dashboard** (not rejected), and the Academic/Classes/People/Assignments nav groups are visible.
- Also confirm **bih.patience@pssnkwen.local / secret** (principal) can log in.
Report "panel guard fix done" + confirm both logins reach the dashboard with the nav visible.
