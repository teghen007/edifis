# DeepSeek Phase C4-backend — Bursar fees overview (balances list + seed charges)

> VPS task (backend). Add a single endpoint that lists every student with their fee balance (so the
> bursar sees who owes), and seed some charges so balances aren't all zero.
> (Fee *issuance* with signature capture is a separate later phase — NOT in scope here.)
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD after changes: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - Run seed after rebuild. END: `git add -A && git commit -m "feat(api): fees balances list + seed charges" && git push`
> - Delete this file; report "C4-backend done" + the curl output.

## 1. `GET /fees/balances` (bursar / principal / vice_principal)
In the `auth:sanctum` group:
```php
Route::get('/fees/balances', [FeesController::class, 'balances'])
    ->middleware('role:bursar|principal|vice_principal')->name('fees.balances');
```
`FeesController@balances` — return every active student with their balance, computed via the same
`BalanceQuery` used by `balance()`. **Required shape:**
```json
[ { "student_id":"uuid", "name":"Bih Grace", "class_name":"Form 3", "balance":50000, "currency":"XAF" } ]
```
- Sort by **balance descending** (biggest debtors first).
- Reuse `BalanceQuery` per student (30 students — fine). Map `name` from given+family like `/students`.

## 2. Seed some charges (so balances are real)
In the demo seeder (idempotent): give roughly **10–12** of the students a non-zero fee balance
(e.g. tuition charges of 25,000–75,000 XAF) using whatever the ledger uses for a debit/charge
(reuse the issuance/ledger action, or insert ledger rows directly — match how `BalanceQuery` reads).
Leave the rest at 0. Make it safe to re-run.

## 3. Deploy + verify (paste)
```bash
cd /opt/edifis && git pull && cd edifis-infra/prod
DC="docker compose -f docker-compose.prod.yml --env-file .env.prod"
$DC up -d --build app horizon
$DC exec -T app php artisan db:seed --class=DemoDataSeeder --force   # adjust seeder name
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"nebaluices@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
curl -s $API/fees/balances -H "Authorization: Bearer $TOK" -H "Accept: application/json" | python3 -c 'import sys,json;d=json.load(sys.stdin);print("count:",len(d));print("with balance:",sum(1 for x in d if x["balance"]>0));print("top:",d[0])'
```
Report "C4-backend done" + the count + how many have a balance + the top row (so the architect
confirms the shape before building the Fees screen).
