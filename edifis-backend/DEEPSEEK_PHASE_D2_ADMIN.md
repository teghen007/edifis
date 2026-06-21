# DeepSeek Phase D2 — Filament Admin panel (the "Settings" page: manage + assign)

> VPS task (backend / Filament web). Build the school-admin CRUD over the D1 academic model — the
> EDIFIS version of Flexio's Settings page. The **System Admin** creates user accounts and wires all
> the assignments (subjects↔stream, students↔stream, teacher→subject→stream, classmaster). Use Filament
> resources + relation managers. The panel is the existing `/staff` Filament panel (already Wisdom-Blue themed).
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD: `... up -d --build app horizon`; then `php artisan migrate --force`, `db:seed --class=DemoDataSeeder --force`,
>   `php artisan filament:optimize`
> - END: `git add -A && git commit -m "feat(admin): filament academic admin + user/assignment management" && git push`
> - Delete this file; report "D2 done" + screenshots of the admin nav + one assignment screen.

## 1. School-admin role + login
- Add a **`school_admin`** role (spatie). Seed one admin user: name "School Admin",
  email `admin@pssnkwen.local`, password `secret`, role `school_admin` (idempotent).
- All resources below: visible to **`school_admin`** AND **`principal`** (use Filament resource
  `canViewAny`/policy or `shouldRegisterNavigation` gated on those roles).

## 2. Filament resources (scaffold with `php artisan make:filament-resource --generate`)
Group them in the nav. Key fields only (add sensible Filament form/table columns):
- **Academic** group: `AcademicYear` (name, is_current), `Section` (name), `Term` (name, academic_year, position),
  `Test` (name, term, position, default_max).
- **Classes & Streams** group: `SchoolClass` (name, level), `Stream` (name, class, section, academic_year, active).
- **Subjects** group: `Subject` (name, code, active).
- **People** group:
  - `Student` (given_name, family_name, active, + a select for current Stream via student_stream).
  - `StaffUser` — a Filament resource over `User` filtered to staff roles. Form: name, email, **password**
    (hashed, required on create), **role** (select: principal, vice_principal, bursar, class_master,
    subject_teacher, discipline_master, secretary, school_admin). This is how the admin **creates accounts**.

## 3. Assignments — Filament Relation Managers (the heart of it)
- On **Stream** resource:
  - **Subjects** relation manager (subject_stream) — attach/detach which subjects this stream offers.
  - **Students** relation manager (student_stream) — enrol/remove students in this stream.
  - **Class Master** — a Select field (users with class_master/subject_teacher role) writing to `class_masters`.
- On **Student** resource:
  - **Subjects** relation manager (student_subject) — the per-student subjects (defaults to stream offerings; admin/class-master can drop/add electives).
- **TeacherAssignment** resource (or a relation manager on StaffUser): rows of (teacher, subject, stream) —
  this is the **scoped permission**: the teacher may only enter marks for these subject+stream pairs.

## 4. Polish
- Nav icons (Lucide/Heroicons), groups ordered: Academic · Classes & Streams · Subjects · People · Assignments.
- Keep the brand: primary `#2563EB`, the EDIFIS logo already set.
- Each list view searchable; relation managers use attach/detach modals.

## 5. Verify
- Log into `https://pssnkwen.myedifis.com/staff` as `admin@pssnkwen.local` / `secret` → you can:
  create a teacher account, open a Stream → attach subjects + enrol students + set class master,
  open a Student → set their subjects, and add a TeacherAssignment.
- `curl -s -o /dev/null -w '%{http_code}\n' https://pssnkwen.myedifis.com/staff/login` → 200.
Report "D2 done" + screenshots (admin dashboard nav + a Stream's relation managers) + confirm the
admin can create a user and make an assignment.
