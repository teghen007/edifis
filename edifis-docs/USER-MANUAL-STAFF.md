# EDIFIS — Staff User Manual

## What EDIFIS does for you

EDIFIS is your school's digital backbone. It tracks enrolment, fees, textbook issuance, attendance, marks, discipline, promotion decisions, timetables, and parent communication — all in one system. It works on campus even when the internet is down, and syncs to the cloud when a connection returns.

---

## Logging in

### On campus (every day)
1. Connect to the school Wi-Fi network.
2. Open your browser and go to **`https://pssnkwen.local/staff`**.
3. Sign in with your email and password.

### Off campus (at home / PEA office)
1. Go to **`https://pssnkwen.edifis.cm/staff`**.
2. Sign in with the same credentials.

### New-device email code
The first time you log in from a new browser or device, you'll receive a 6-digit code by email. Enter it to verify your device. After that, the device is trusted for 90 days and you won't need the code again. If you don't have an email on file, this step is skipped.

---

## Roles — what you can do

### Principal
You have institution-wide authority, and authority with a trail.

| Task | Where |
|------|-------|
| Approve the master timetable | **Timetable** → review unapproved entries → Approve |
| Run the VACUUM AI co-pilot | **VACUUM** → type a question (e.g. "Who is borderline?") → get an answer + records |
| Execute a VACUUM command | **VACUUM** → Command tab → choose action (correct_mark, override_promotion, deactivate_account) → provide reason → confirm |
| View school-wide attendance | **Attendance** → open any session tally |
| View all marks | **Marks** → browse by student, class, or sequence |
| Override promotion | **Promotion** → find the decision → Override → provide reason (this is audited) |
| Deactivate a staff account | **VACUUM** → Command → deactivate_account → reason + confirm (account is retained — data is never lost) |

**VACUUM commands are recorded in the permanent audit trail** with your name, the time, what changed, and your reason. Finance data is never directly editable via VACUUM.

### Vice Principal
You own the timetable and school calendar and oversee discipline.

| Task | Where |
|------|-------|
| Create/edit timetable entries | **Timetable** → New entry → fill class, subject, teacher, period → Save |
| Submit timetable for approval | Entries are marked `is_approved: false` until the Principal approves them |
| Manage the school calendar | **Calendar** → add events (exam dates, holidays, meetings) |
| View school-wide attendance and marks | Same as Principal — read access to all classes |

### Bursar
You handle money and items.

| Task | Where |
|------|-------|
| Import the fees/rubric catalogue | **Issuance** → Import (from Excel) |
| Issue textbooks/uniforms to a student | **Issuance** → New → select student → check items → sign → Issue |
| Return a returned item | **Issuance** → find the issued item → Return → provide reason |
| View student balances | **Fees** → search by student → balance is computed from all ledger entries |
| View the fee ledger | **Fees** → all transactions, sorted by date |
| Print receipts | **Fees** → select student → Print Receipt |
| Enrol a student | **Students** → New → fill details + guardian consent → Enrol |

**Important:** Money is append-only. You cannot edit or delete a fee or an issuance — corrections are new entries (returns, credits). Every issuance batch is signed (canvas signature on the field workstation or signature pad on web).

### Class Master
You manage your assigned class.

| Task | Where |
|------|-------|
| Open an attendance session | **Field Attendance** (`/field/attendance`) → enter class + subject → Open |
| Scan (QR) or type (manual) student attendance | Type student ID or scan QR card → Enter → scan recorded |
| Override a student as present (forgot ID card) | Toggle to **Override** → provide reason → scan → recorded (audited) |
| Close the session | Close Session → tally is saved |
| Print an attendance register | From the attendance screen → Print |
| Enter marks for your class | **Marks** → select student, subject, sequence → enter score → Save |
| Record discipline/exeat notes | Recorded in the attendance/discipline module |

**Override is default-on** — if a student is present but cardless, you record them. The reason is stored in the audit trail.

### Subject Teacher
You own marks for your subject and take attendance for your classes.

| Task | Where |
|------|-------|
| Take QR attendance | Same as Class Master — open session → scan |
| Enter marks for your subject | **Marks** → New → fill student, subject, class, sequence, score → Save |
| View marks you've entered | **Marks** → filtered to your subject/class automatically |

**Mark ownership:** A mark is "owned" by the teacher who entered it. If another teacher or sync tries to overwrite it with a conflicting value, the conflict is surfaced (cloud-wins) and visible to you. Your marks are never silently changed.

### Secretary
You handle registration and demographics.

| Task | Where |
|------|-------|
| Register a new student | **Students** → New → fill student details + guardian consent → Enrol |
| Update student demographics (address, parent phone) | **Students** → find student → Edit |
| Print report cards / registers | **Documents** → select student, sequence → Print |

### Discipline Master
You handle exeats and discipline tracking.

| Task | Where |
|------|-------|
| Record exeats | **Discipline** module (coming in Phase 13) |
| View school-wide attendance | **Attendance** → all sessions visible |

---

## Working on campus vs off campus

- **On campus:** The field workstation (`/field/attendance`, `/field/issuance`) is served by the school's local node. QR scanning works with the classroom camera. All data is stored locally and syncs up when internet returns.
- **Off campus (cloud):** The same web app is available at `https://pssnkwen.edifis.cm/staff`. Field workstations may not work well off campus (QR scanning needs the local network).

---

## Where to find your screens

| Module | Web URL | Who can access |
|--------|---------|----------------|
| Staff dashboard | `/staff` | All staff |
| Students | `/staff/students` | Secretary, Bursar, Principal |
| Issuance | `/staff/issuances` | Bursar |
| Field issuance | `/field/issuance` | Bursar |
| Fees | `/staff/fees` | Bursar, Principal |
| Marks | `/staff/marks` | Subject Teachers, Class Masters, Principal |
| Promotion | `/staff/promotions` | Principal |
| Timetable | `/staff/timetables` | VP, Principal |
| Calendar | `/staff/calendar` | All staff |
| VACUUM | `/staff/vacuum` | Principal (only) |
| Field attendance | `/field/attendance` | Class Masters, Subject Teachers |
| Documents | `/staff/documents` | Secretary, Bursar, Principal |

---

## Quick tips

- **Your PIN is private** — never share it. Staff use passwords; parents use PINs.
- **Offline work:** If the internet drops, keep working. The node stores everything locally and syncs when the network returns.
- **Signature:** Every issuance batch requires a signature (canvas pad on the field workstation). This is your legal record.
- **Audit trail:** Every VACUUM command, every mark change, every issuance — everything that touches money or marks is recorded in the audit log. You can't delete it, and that's by design.
- **Forgot password?** Contact your school's IT teacher or the PEA administrator to reset it.
