# DeepSeek — Fix seeder so role model_type isn't over-escaped + remove diag route

> VPS task (backend). Root cause of the admin-login bug (now patched in the DB by the architect): the
> D2 seeder assigned the `school_admin` role with `model_type = 'App\\Models\\User'` (double backslashes)
> instead of `App\Models\User`. Spatie's `roles()` relationship filters by the real morph class, so the
> role was invisible → `canAccessPanel` false. Fix the seeder so re-running `db:seed` can't re-corrupt it.
> Also: the architect already removed the temp `/_diag/login` route from `routes/api.php` (pull picks it up).
>
> ## RULES
> - START: `cd /opt/edifis && git pull`   (this removes the diag route)
> - REBUILD: `... up -d --build app horizon`
> - END: `git add -A && git commit -m "fix(seed): assign roles via Eloquent (correct model_type)" && git push`
> - Delete this file; report "seeder fix done".

## 1. Find the bad role assignment
`grep -rn "model_has_roles\|model_type\|assignRole\|App.\+Models.\+User" database/seeders` — look for where
`school_admin` (and any StaffUser/staff role) is attached. The bug is almost certainly a **raw insert**
like `DB::table('model_has_roles')->insert(['role_id'=>..., 'model_type'=>'App\\\\Models\\\\User', 'model_id'=>$id])`
(the escaped string becomes `App\\Models\\User` in the DB), OR a string built with extra escaping.

## 2. Replace with Eloquent role assignment
Use spatie's API so the morph class is correct automatically:
```php
$user->assignRole('school_admin');   // sets model_type = App\Models\User correctly
```
(or `$user->syncRoles([...])`). Do NOT hand-write `model_type`. Apply the same to ANY place the seeder
inserts model_has_roles rows by hand.

## 3. Make the seed self-heal (idempotent guard)
Optionally add, at the end of the seeder, a normalisation so old bad rows get fixed on any future seed:
```php
\DB::table('model_has_roles')->where('model_type', 'App\\\\Models\\\\User')->update(['model_type' => 'App\\Models\\User']);
```
(PHP: `'App\\\\Models\\\\User'` is the literal `App\\Models\\User`; `'App\\Models\\User'` is `App\Models\User`.)

## 4. Verify
```bash
API=https://pssnkwen.myedifis.com/api
DC="docker compose -f docker-compose.prod.yml --env-file .env.prod"
# re-run the seed and confirm it does NOT re-break the admin
$DC exec -T app php artisan db:seed --class=DemoDataSeeder --force
$DC exec -T postgres psql -U edifis -d edifis -c "SELECT DISTINCT model_type FROM model_has_roles;"
# /_diag/login route should now be GONE:
curl -s -o /dev/null -w 'diag route (expect 404): %{http_code}\n' "$API/_diag/login?e=x&p=y"
```
`SELECT DISTINCT model_type` must show **only** `App\Models\User` (no double-backslash variant), and the
diag route must be 404. Confirm `admin@pssnkwen.local / secret` still logs into `/staff`.
Report "seeder fix done" + the distinct model_type values.
