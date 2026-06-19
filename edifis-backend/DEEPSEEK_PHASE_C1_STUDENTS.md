# DeepSeek Phase C1 — Student roster read endpoint (foundation for role tools)

> VPS task (backend). Staff need to SEE students (the app only had summary counts). Add a read
> endpoint that lists students for the school. This is the base the marks/attendance/fees screens reuse.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - Backend code baked into image → after changes REBUILD:
>   `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - END: `git add -A && git commit -m "feat(api): GET /students roster" && git push`
> - Delete this file; report "C1 done" + the curl output (the JSON shape).

## 1. Route — `GET /students` (any staff role)
In the `auth:sanctum` group in `routes/api.php`, add:
```php
Route::get('/students', [StudentController::class, 'index'])
    ->middleware('role:principal|vice_principal|secretary|bursar|class_master|subject_teacher|discipline_master')
    ->name('students.index');
```
(Keep the existing `POST /students`.)

## 2. `StudentController@index`
Return the active students for this tenant, lightweight and sorted by name. **Required shape**
(use exactly these keys so the app can bind to them; map from whatever the real columns are):
```json
[
  { "id": "uuid", "name": "Bih Grace", "class_name": "Form 1", "active": true }
]
```
- `name`: full display name (combine given/family names if the model stores them separately).
- `class_name`: the student's class/level label if available; if there's no such column, return `""`.
- Sort by `name` asc. Return all active students (the demo has ~30 — no pagination needed yet).
- Example:
```php
public function index(\Illuminate\Http\Request $request)
{
    $students = \App\Domain\Students\Models\Student::query()
        ->where('active', true)
        ->orderBy('name')   // adjust if the name column differs
        ->get()
        ->map(fn ($s) => [
            'id'         => $s->id,
            'name'       => $s->name,                 // adjust to real column(s)
            'class_name' => $s->class_name ?? '',     // adjust / '' if none
            'active'     => (bool) $s->active,
        ]);
    return response()->json($students);
}
```

## 3. Deploy + verify (paste the JSON)
```bash
cd /opt/edifis && git pull && cd edifis-infra/prod
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"rita.awah@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
echo -n "count: "; curl -s $API/students -H "Authorization: Bearer $TOK" -H "Accept: application/json" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(len(d));print(d[0] if d else "empty")'
```
Report "C1 done" + the count (expect ~30) + the first student object (so the architect confirms the
shape before building the app's Students screen).
