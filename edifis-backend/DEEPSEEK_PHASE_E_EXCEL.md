# DeepSeek Phase E — Scoped Excel marks pipeline (download template → fill → upload → ingest)

> VPS task (backend). Teachers should NOT type marks one-by-one. They download a **pre-filled Excel
> mark-sheet** scoped to THEIR assignment (stream + subject), fill the Marks column, and upload it →
> the server validates + ingests. Use `maatwebsite/excel` (already installed). Also fix a guard bug.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD: `... up -d --build app horizon`
> - END: `git add -A && git commit -m "feat: scoped excel marks pipeline + dual-guard user creation" && git push`
> - Delete this file; report "Phase E done" + the curl/test outputs.

## 0. Fix dual-guard role assignment (so admin-created users work in BOTH app + panel)
Roles now exist on BOTH `web` and `sanctum` guards. When the **StaffUser** Filament resource creates a
user and assigns a role, make it assign on **both** guards so the user works in the app (sanctum) AND
shows in the panel (web). Simplest: after the user is created, in the resource's `afterCreate`/mutate:
```php
$user->syncRoles([]);
foreach (['web','sanctum'] as $g) {
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => $data['role'], 'guard_name' => $g]);
    \DB::table('model_has_roles')->insertOrIgnore(['role_id'=>$role->id,'model_type'=>$user->getMorphClass(),'model_id'=>$user->id]);
}
```
(Use the real morph class `App\Models\User`. Keep it idempotent.)

## 1. Scope helper (already on User from D1): `teachesSubjectInStream($subjectId,$streamId)`
A teacher may only download/upload for (subject, stream) pairs they're assigned (via `teacher_assignments`).
Principal / vice_principal / school_admin may do any.

## 2. `GET /marks/template?stream_id=&subject_id=&test_id=` (auth, staff)
Returns an `.xlsx` download (`Excel::download(new MarkSheetExport(...), 'marksheet.xlsx')`):
- **Authorize**: `abort_unless($user->teachesSubjectInStream($subjectId,$streamId) || $user->hasAnyRoleName(['principal','vice_principal','school_admin']), 403)`.
- **Sheet "Marks"**: a header area with School name, Stream name, Subject name, Test name, Max score,
  then a table with columns: `student_id` | `Student Name` | `Marks`. One row per student **enrolled in
  the stream AND taking the subject** (join `student_stream` + `student_subject`). The `Marks` column blank.
- **Hidden "meta" sheet** (or hidden columns): store `stream_id`, `subject_id`, `test_id`, `max` so the
  upload is self-describing. (If hiding a sheet is awkward, put these in fixed cells of row 1 and read them back.)
- Implement `App\Exports\MarkSheetExport` with `FromCollection`/`WithHeadings`/`WithMultipleSheets` as needed.

## 3. `POST /marks/upload` (auth, staff; multipart file=the filled .xlsx)
- Read the meta (stream_id, subject_id, test_id, max) from the uploaded file.
- **Authorize** the same way (teacher owns subject+stream, or principal/vp/admin).
- For each student row that has a Marks value:
  - validate the student is enrolled in the stream + takes the subject, and `0 <= marks <= max` (numeric).
  - ingest via the existing `RecordMark` action (generate `id`=uuid, `revision`=uuid, student_id, subject_id,
    class_id (the stream's class), sequence = the test name, score=marks, max_score=max,
    owner_teacher_id=$user->id, published=true).
- Return JSON `{ "saved": N, "skipped": [...], "errors": [{row, reason}] }` (don't fail the whole file on one bad row).
- Implement `App\Imports\MarkSheetImport` (`ToCollection` so you can read meta + rows).

## 4. Verify (paste)
```bash
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"ngufor.calvin@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
A="Authorization: Bearer $TOK"; J="Accept: application/json"
# ngufor.calvin is assigned Maths in Form 4 & Form 5 (D1). Get those ids:
CLASS=$(curl -s $API/classes -H "$A" -H "$J" | python3 -c 'import sys,json;d=json.load(sys.stdin);print([c["id"] for c in d if c["name"]=="Form 4"][0])')
STREAM=$(curl -s $API/streams -H "$A" -H "$J" | python3 -c 'import sys,json;d=json.load(sys.stdin);print([s["id"] for s in d if s["class_name"]=="Form 4"][0])')
SUBJ=$(curl -s $API/subjects -H "$A" -H "$J" | python3 -c 'import sys,json;d=json.load(sys.stdin);print([s["id"] for s in d if s["name"]=="Mathematics"][0])')
TEST=$(curl -s $API/terms -H "$A" -H "$J" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d[0]["tests"][0]["id"])')
echo "template download HTTP + size:"
curl -s -o /tmp/ms.xlsx -w '%{http_code} %{size_download} bytes\n' "$API/marks/template?stream_id=$STREAM&subject_id=$SUBJ&test_id=$TEST" -H "$A"
echo "(open /tmp/ms.xlsx to confirm it has the students + meta)"
# a teacher NOT assigned this stream/subject should get 403
```
Report "Phase E done" + the template HTTP/size + confirm the xlsx contains the Form 4 students,
School/Subject/Test headers, and a blank Marks column. (Upload test can be done from the app side next.)
