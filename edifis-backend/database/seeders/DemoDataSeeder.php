<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academics\Models\AcademicYear;
use App\Domain\Academics\Models\Mark;
use App\Domain\Academics\Models\SchoolClass;
use App\Domain\Academics\Models\Section;
use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\Term;
use App\Domain\Academics\Models\Test;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Students\Models\Student;
use App\Domain\Timetable\Models\CalendarEvent;
use App\Domain\Timetable\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class DemoDataSeeder extends Seeder
{
    private const CLASS_IDS = [
        'f1a' => 'f1a00000-0000-0000-0000-000000000001',
        'f1b' => 'f1b00000-0000-0000-0000-000000000001',
        'f2a' => 'f2a00000-0000-0000-0000-000000000001',
    ];

    private const SUBJECT_IDS = [
        'math'    => 'a1a00000-0000-0000-0000-000000000001',
        'english' => 'a1b00000-0000-0000-0000-000000000001',
        'biology' => 'a1c00000-0000-0000-0000-000000000001',
        'history' => 'a1d00000-0000-0000-0000-000000000001',
        'physics' => 'a1e00000-0000-0000-0000-000000000001',
    ];

    private array $studentIds = [];

    private function seedClasses(): void
    {
        $classes = [
            ['id' => (string) Uuid::uuid7(), 'name' => 'Form 1',        'level' => 1],
            ['id' => (string) Uuid::uuid7(), 'name' => 'Form 2',        'level' => 2],
            ['id' => (string) Uuid::uuid7(), 'name' => 'Form 3',        'level' => 3],
            ['id' => (string) Uuid::uuid7(), 'name' => 'Form 4',        'level' => 4],
            ['id' => (string) Uuid::uuid7(), 'name' => 'Form 5',        'level' => 5],
            ['id' => (string) Uuid::uuid7(), 'name' => 'Lower Sixth',   'level' => 6],
            ['id' => (string) Uuid::uuid7(), 'name' => 'Upper Sixth',   'level' => 7],
        ];

        $count = 0;
        foreach ($classes as $c) {
            $exists = SchoolClass::where('name', $c['name'])->exists();
            if (! $exists) {
                SchoolClass::create($c);
                $count++;
            }
        }
        $this->command->info("[classes] {$count} new / " . count($classes) . " total");
    }

    private function seedSubjects(): void
    {
        $subjects = [
            ['name' => 'Biology',           'code' => 'BIO'],
            ['name' => 'Chemistry',         'code' => 'CHE'],
            ['name' => 'Citizenship',       'code' => 'CIT'],
            ['name' => 'Computer Science',  'code' => 'CSC'],
            ['name' => 'Economics',         'code' => 'ECO'],
            ['name' => 'English Language',  'code' => 'ENG'],
            ['name' => 'French',            'code' => 'FRE'],
            ['name' => 'Geography',         'code' => 'GEO'],
            ['name' => 'History',           'code' => 'HIS'],
            ['name' => 'Literature in English', 'code' => 'LIT'],
            ['name' => 'Mathematics',       'code' => 'MAT'],
            ['name' => 'Physics',           'code' => 'PHY'],
            ['name' => 'Religious Studies', 'code' => 'RES'],
        ];

        $count = 0;
        foreach ($subjects as $s) {
            $exists = Subject::where('code', $s['code'])->exists();
            if (! $exists) {
                Subject::create([
                    'id' => (string) Uuid::uuid7(),
                    'name' => $s['name'],
                    'code' => $s['code'],
                    'coefficient' => $this->coefficientFor($s['name']),
                ]);
                $count++;
            }
        }
        $this->command->info("[subjects] {$count} new / " . count($subjects) . " total");
    }

    private function assignStudentClasses(): void
    {
        $classes = SchoolClass::where('active', true)
            ->where('level', '<=', 5)
            ->orderBy('level')
            ->get();

        if ($classes->isEmpty()) {
            $this->command->warn('[assign] no classes found — run seedClasses first.');
            return;
        }

        $classIds = $classes->pluck('id')->all();
        $updated = 0;

        foreach ($this->studentIds as $i => $studentId) {
            $student = Student::find($studentId);
            if ($student && $student->class_id === null) {
                $student->class_id = $classIds[$i % count($classIds)];
                $student->save();
                $updated++;
            }
        }

        $this->command->info("[assign] {$updated} students assigned to classes.");
    }

    public function run(): void
    {
        $this->command->info('=== DemoDataSeeder ===');

        \DB::table('model_has_roles')->where('model_type', 'App\\\\Models\\\\User')->update(['model_type' => 'App\\Models\\User']);

        $this->seedClasses();
        $this->seedSubjects();
        $this->seedStudents();
        $this->assignStudentClasses();
        $this->seedMarks();
        $this->seedAttendance();
        $this->seedAcademicCore();
        $this->seedFees();
        $this->seedTimetable();
        $this->seedCalendarEvents();
        $this->seedParent();
        $this->seedAdmin();
        $this->seedGradeRules();

        $this->command->info('Demo data seeded successfully.');
    }

    private function seedStudents(): void
    {
        $studentNames = [
            ['given' => 'Goodness', 'family' => 'Shei'],
            ['given' => 'John', 'family' => 'Tansi'],
            ['given' => 'Miriam', 'family' => 'Ndefru'],
            ['given' => 'Emmanuel', 'family' => 'Neba'],
            ['given' => 'Precious', 'family' => 'Nformi'],
            ['given' => 'Brandon', 'family' => 'Nji'],
            ['given' => 'Favour', 'family' => 'Asong'],
            ['given' => 'Samuel', 'family' => 'Wandum'],
            ['given' => 'Esther', 'family' => 'Ndikum'],
            ['given' => 'Elvis', 'family' => 'Ndifon'],
            ['given' => 'Mercy', 'family' => 'Bih'],
            ['given' => 'Frankline', 'family' => 'Nyingcho'],
            ['given' => 'Grace', 'family' => 'Kongnyuy'],
            ['given' => 'Kelvin', 'family' => 'Ngwa'],
            ['given' => 'Patience', 'family' => 'Fonyuy'],
            ['given' => 'Roland', 'family' => 'Nformi'],
            ['given' => 'Ruth', 'family' => 'Tita'],
            ['given' => 'Stephen', 'family' => 'Ndi'],
            ['given' => 'Vivian', 'family' => 'Ndip'],
            ['given' => 'Collins', 'family' => 'Nkwelle'],
            ['given' => 'Amara', 'family' => 'Chia'],
            ['given' => 'Blessing', 'family' => 'Wirba'],
            ['given' => 'Cynthia', 'family' => 'Ntemuse'],
            ['given' => 'Delphine', 'family' => 'Awah'],
            ['given' => 'Eric', 'family' => 'Tamanjong'],
            ['given' => 'Florence', 'family' => 'Lukong'],
            ['given' => 'George', 'family' => 'Mokom'],
            ['given' => 'Hannah', 'family' => 'Anu'],
            ['given' => 'Ivan', 'family' => 'Shangsi'],
            ['given' => 'Joyce', 'family' => 'Fondzenyuy'],
        ];

        $classIds = array_values(self::CLASS_IDS);
        $count = 0;

        foreach ($studentNames as $i => $name) {
            $student = Student::firstOrCreate(
                ['given_name' => $name['given'], 'family_name' => $name['family']],
                [
                    'id' => (string) Uuid::uuid7(),
                    'current_class_id' => $classIds[$i % count($classIds)],
                    'sex' => in_array($name['given'], ['Emmanuel', 'John', 'Brandon', 'Samuel', 'Elvis', 'Frankline', 'Kelvin', 'Roland', 'Stephen', 'Collins', 'Eric', 'George', 'Ivan']) ? 'M' : 'F',
                    'enrolled_at' => now()->subMonths(6),
                    'active' => true,
                ]
            );

            $this->studentIds[] = $student->id;
            if ($student->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->command->info("[students] {$count} new / " . count($this->studentIds) . " total");
    }

    private function seedMarks(): void
    {
        $oldSubjects = array_values(self::SUBJECT_IDS);
        $realSubjects = Subject::orderBy('code')->get();
        $oldClassIds = array_values(self::CLASS_IDS);
        $realClasses = SchoolClass::where('active', true)->orderBy('level')->get();

        if ($realSubjects->isNotEmpty() && $realClasses->isNotEmpty()) {
            $backfill = 0;
            foreach ($oldSubjects as $oi => $oldId) {
                $realId = $realSubjects[$oi % count($realSubjects)]->id ?? null;
                if ($realId && $oldId !== $realId) {
                    $backfill += Mark::where('subject_id', $oldId)->update(['subject_id' => $realId]);
                }
            }
            foreach ($oldClassIds as $oi => $oldId) {
                $realId = $realClasses[$oi % count($realClasses)]->id ?? null;
                if ($realId && $oldId !== $realId) {
                    $backfill += Mark::where('class_id', $oldId)->update(['class_id' => $realId]);
                }
            }
            $backfill += Mark::where('sequence', '2026-T1-Seq1')->update(['sequence' => 'Sequence 1']);
            if ($backfill > 0) {
                $this->command->info("[marks] {$backfill} rows backfilled to real IDs + test names.");
            }
        }

        $existing = Mark::where('sequence', 'Sequence 1')->count();
        if ($existing > count($this->studentIds)) {
            $this->command->info('[marks] already seeded — skipped.');
            return;
        }

        $classIds = array_values(self::CLASS_IDS);
        $teacher = User::where('email', 'ngufor.calvin@pssnkwen.local')->first();
        $teacherId = $teacher?->id ?? '00000000-0000-0000-0000-000000000999';
        $subjects = Subject::orderBy('code')->take(2)->pluck('id')->all();
        $count = 0;

        foreach ($this->studentIds as $i => $studentId) {
            foreach ($subjects as $j => $subjectId) {
                $exists = Mark::where('student_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->where('sequence', 'Sequence 1')
                    ->exists();

                if (! $exists) {
                    $score = random_int(8, 20);
                    Mark::create([
                        'id' => (string) Uuid::uuid7(),
                        'revision' => 'r1',
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                        'class_id' => $classIds[$i % count($classIds)],
                        'sequence' => 'Sequence 1',
                        'owner_teacher_id' => $teacherId,
                        'score' => $score,
                        'max_score' => 20.0,
                        'recorded_at' => now()->subDays(random_int(1, 30)),
                        'published' => true,
                    ]);
                    $count++;
                }
            }
        }

        $this->command->info("[marks] {$count} marks seeded.");
    }

    private function seedAttendance(): void
    {
        $today = now()->toDateString();
        $existing = AttendanceSession::whereDate('opened_at', $today)->count();
        if ($existing > 0) {
            $this->command->info('[attendance] today sessions already exist — skipped.');
            return;
        }

        $teacher = User::where('email', 'ngufor.calvin@pssnkwen.local')->first();
        $teacherId = $teacher?->id ?? '00000000-0000-0000-0000-000000000999';
        $classIds = array_values(self::CLASS_IDS);
        $chunkSize = (int) ceil(count($this->studentIds) / count($classIds));
        $classChunks = array_chunk($this->studentIds, $chunkSize);

        foreach ($classIds as $ci => $classId) {
            $classStudents = $classChunks[$ci] ?? [];
            $headcount = count($classStudents);
            if ($headcount === 0) $headcount = 10;

            $sessionId = (string) Uuid::uuid7();
            AttendanceSession::create([
                'id' => $sessionId,
                'class_id' => $classId,
                'subject_id' => self::SUBJECT_IDS['math'],
                'teacher_id' => $teacherId,
                'period' => '08:00-09:00',
                'headcount' => $headcount,
                'status' => 'closed',
                'opened_at' => today()->setHour(8)->setMinute(0),
                'closed_at' => today()->setHour(9)->setMinute(0),
            ]);

            $scanCount = max(1, $headcount - random_int(0, 2));
            foreach (array_slice($classStudents, 0, $scanCount) as $sid) {
                AttendanceEvent::create([
                    'id' => (string) Uuid::uuid7(),
                    'revision' => 'r1',
                    'session_id' => $sessionId,
                    'student_id' => $sid,
                    'scanned_at' => today()->setHour(8)->addMinutes(random_int(0, 30)),
                    'status' => 'present',
                    'source' => 'seeder',
                ]);
            }
        }

        $this->command->info('[attendance] ' . count($classIds) . ' sessions + scans seeded.');
    }

    private function seedFees(): void
    {
        $existing = IssueEvent::where('status', 'issued')->count();
        if ($existing > 10) {
            $this->command->info('[fees] issue events already seeded — skipped.');
        } else {
            $items = CatalogueItem::all();
            if ($items->isEmpty()) {
                $this->command->warn('[fees] no catalogue items found — run LabSeeder first.');
            } else {
                $staff = User::where('email', 'nebaluices@pssnkwen.local')->first();
                $staffId = $staff?->id ?? '00000000-0000-0000-0000-000000000999';
                $count = 0;

                foreach (array_slice($this->studentIds, 0, 15) as $studentId) {
                    $item = $items->random();
                    $cost = $item->cost;

                    $eventId = (string) Uuid::uuid7();
                    $batchId = (string) Uuid::uuid7();
                    IssueEvent::create([
                        'id' => $eventId,
                        'revision' => 'r1',
                        'student_id' => $studentId,
                        'catalogue_item_id' => $item->id,
                        'cost' => $cost,
                        'issued_at' => now()->subDays(random_int(1, 60)),
                        'staff_id' => $staffId,
                        'batch_id' => $batchId,
                        'status' => 'issued',
                    ]);
                    $count++;

                    if (random_int(0, 1) === 1) {
                        LedgerEntry::create([
                            'id' => (string) Uuid::uuid7(),
                            'student_id' => $studentId,
                            'source_event_id' => $eventId,
                            'amount' => $cost,
                            'posted_at' => now()->subDays(random_int(1, 30)),
                        ]);
                    }
                }

                $this->command->info("[fees] {$count} issue events seeded.");
            }
        }

        $this->seedTuitionCharges();
    }

    private function seedTuitionCharges(): void
    {
        if (count($this->studentIds) < 10) {
            return;
        }

        $firstId = $this->studentIds[0];
        $existing = LedgerEntry::where('student_id', $firstId)
            ->where('amount', '>=', 25000)
            ->count();
        if ($existing > 0) {
            $this->command->info('[tuition] charges already seeded — skipped.');
            return;
        }

        $count = 0;
        $targetCount = random_int(10, 12);
        $studentSlice = array_slice($this->studentIds, 0, $targetCount);

        foreach ($studentSlice as $studentId) {
            $amount = random_int(25000, 75000);
            LedgerEntry::create([
                'id' => (string) Uuid::uuid7(),
                'student_id' => $studentId,
                'source_event_id' => (string) Uuid::uuid7(),
                'amount' => $amount,
                'posted_at' => now()->subDays(random_int(1, 90)),
            ]);
            $count++;
        }

        $this->command->info("[tuition] {$count} students seeded with tuition charges (25 000 - 75 000 XAF).");
    }

    private function seedTimetable(): void
    {
        $badClasses = array_values(self::CLASS_IDS);
        $badSubjects = array_values(self::SUBJECT_IDS);
        $deleted = TimetableEntry::whereIn('class_id', $badClasses)
            ->orWhereIn('subject_id', $badSubjects)
            ->delete();

        $realClasses = SchoolClass::where('active', true)->orderBy('level')->get();
        $realSubjects = Subject::all();
        $teacher = User::where('email', 'ngufor.calvin@pssnkwen.local')->first();
        $teacherId = $teacher?->id ?? '00000000-0000-0000-0000-000000000999';

        if ($realClasses->isEmpty() || $realSubjects->isEmpty() || !$teacher) {
            $this->command->warn('[timetable] missing real classes/subjects/teacher — skipped.');
            return;
        }

        $existing = TimetableEntry::count();
        if ($existing >= 10) {
            $this->command->info('[timetable] already seeded with real ids — skipped.');
            return;
        }

        $classIds = $realClasses->pluck('id')->all();
        $subjectIds = $realSubjects->pluck('id')->all();
        $rooms = ['Room A', 'Room B', 'Lab 1', 'Lab 2', 'Hall'];
        $count = 0;

        $entries = [
            ['class_idx' => 0, 'subj_idx' => 0, 'day' => '1', 'start' => '08:00', 'end' => '09:00', 'room' => 0],
            ['class_idx' => 0, 'subj_idx' => 1, 'day' => '1', 'start' => '09:00', 'end' => '10:00', 'room' => 1],
            ['class_idx' => 0, 'subj_idx' => 2, 'day' => '2', 'start' => '08:00', 'end' => '09:00', 'room' => 0],
            ['class_idx' => 1, 'subj_idx' => 3, 'day' => '1', 'start' => '08:00', 'end' => '09:00', 'room' => 1],
            ['class_idx' => 1, 'subj_idx' => 4, 'day' => '2', 'start' => '09:00', 'end' => '10:00', 'room' => 2],
            ['class_idx' => 2, 'subj_idx' => 0, 'day' => '3', 'start' => '08:00', 'end' => '09:00', 'room' => 3],
            ['class_idx' => 2, 'subj_idx' => 1, 'day' => '3', 'start' => '09:00', 'end' => '10:00', 'room' => 4],
            ['class_idx' => 2, 'subj_idx' => 2, 'day' => '4', 'start' => '08:00', 'end' => '09:00', 'room' => 0],
            ['class_idx' => 3, 'subj_idx' => 3, 'day' => '4', 'start' => '09:00', 'end' => '10:00', 'room' => 1],
            ['class_idx' => 3, 'subj_idx' => 4, 'day' => '5', 'start' => '08:00', 'end' => '09:00', 'room' => 2],
        ];

        foreach ($entries as $i => $e) {
            $classId = $classIds[$e['class_idx'] % count($classIds)];
            $subjectId = $subjectIds[$e['subj_idx'] % count($subjectIds)];

            TimetableEntry::create([
                'id' => (string) Uuid::uuid7(),
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'day_of_week' => $e['day'],
                'period_start' => $e['start'],
                'period_end' => $e['end'],
                'room' => $rooms[$e['room']],
                'created_by' => $teacherId,
                'is_approved' => $i < 7,
            ]);
            $count++;
        }

        $pending = $count - 7;
        $this->command->info("[timetable] {$deleted} old placeholder rows deleted, {$count} entries seeded ({$pending} pending).");
    }

    private function seedCalendarEvents(): void
    {
        $existing = CalendarEvent::count();
        if ($existing >= 3) {
            $this->command->info('[calendar] already seeded — skipped.');
            return;
        }

        $teacher = User::where('email', 'ngufor.calvin@pssnkwen.local')->first();
        $teacherId = $teacher?->id ?? '00000000-0000-0000-0000-000000000999';

        CalendarEvent::firstOrCreate(
            ['title' => 'Staff Meeting'],
            [
                'id' => (string) Uuid::uuid7(),
                'type' => 'meeting',
                'starts_at' => now()->addDays(2)->setHour(14)->setMinute(0),
                'ends_at' => now()->addDays(2)->setHour(16)->setMinute(0),
                'scope' => 'school',
                'created_by' => $teacherId,
            ]
        );

        CalendarEvent::firstOrCreate(
            ['title' => 'Exam Week'],
            [
                'id' => (string) Uuid::uuid7(),
                'type' => 'exam',
                'starts_at' => now()->addDays(14)->setHour(8)->setMinute(0),
                'ends_at' => now()->addDays(18)->setHour(12)->setMinute(0),
                'scope' => 'school',
                'created_by' => $teacherId,
            ]
        );

        CalendarEvent::firstOrCreate(
            ['title' => 'Form 2A Field Trip'],
            [
                'id' => (string) Uuid::uuid7(),
                'type' => 'event',
                'starts_at' => now()->addDays(7)->setHour(9)->setMinute(0),
                'ends_at' => now()->addDays(7)->setHour(15)->setMinute(0),
                'scope' => 'class',
                'class_id' => self::CLASS_IDS['f2a'],
                'created_by' => $teacherId,
            ]
        );

        $this->command->info('[calendar] 3 events seeded.');
    }

    private function seedParent(): void
    {
        $parent = User::firstOrCreate(
            ['phone' => '+237600000001'],
            [
                'id' => (string) Uuid::uuid7(),
                'name' => 'Demo Parent',
                'email' => 'demoparent@pssnkwen.local',
                'password' => Hash::make('secret'),
                'pin_hash' => Hash::make('1234'),
                'active' => true,
            ]
        );

        if (! $parent->hasRole('parent')) {
            $parent->assignRole('parent');
        }

        $linked = 0;
        if (count($this->studentIds) >= 2) {
            foreach (array_slice($this->studentIds, 0, 2) as $studentId) {
                $exists = DB::table('guardian_student')
                    ->where('guardian_id', $parent->id)
                    ->where('student_id', $studentId)
                    ->exists();

                if (! $exists) {
                    DB::table('guardian_student')->insert([
                        'id' => (string) Uuid::uuid7(),
                        'guardian_id' => $parent->id,
                        'student_id' => $studentId,
                        'relationship' => 'guardian',
                        'is_primary' => $linked === 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $linked++;
                }
            }
        }

        $this->command->info("[parent] 1 demo parent (phone +237600000001, PIN 1234) linked to {$linked} students.");
    }

    private function seedAcademicCore(): void
    {
        if (AcademicYear::count() > 0) {
            $this->command->info('[academic_core] already seeded — skipped.');
            return;
        }

        $year = AcademicYear::create([
            'id' => (string) Uuid::uuid7(),
            'name' => '2025-2026',
            'is_current' => true,
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-15',
        ]);

        $section = Section::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Secondary',
        ]);

        $classes = SchoolClass::where('active', true)->orderBy('level')->get();
        $streamIds = [];
        foreach ($classes as $class) {
            $stream = Stream::create([
                'id' => (string) Uuid::uuid7(),
                'name' => $class->name,
                'class_id' => $class->id,
                'section_id' => $section->id,
                'academic_year_id' => $year->id,
                'active' => true,
            ]);
            $streamIds[] = $stream->id;
        }

        $termNames = ['Term 1', 'Term 2', 'Term 3'];
        $termIds = [];
        foreach ($termNames as $pos => $name) {
            $term = Term::create([
                'id' => (string) Uuid::uuid7(),
                'name' => $name,
                'academic_year_id' => $year->id,
                'position' => $pos + 1,
            ]);
            $termIds[] = $term->id;

            for ($seq = 1; $seq <= 2; $seq++) {
                $testPos = ($pos * 2) + $seq;
                Test::create([
                    'id' => (string) Uuid::uuid7(),
                    'name' => 'Sequence ' . $testPos,
                    'term_id' => $term->id,
                    'position' => $testPos,
                    'default_max' => 20,
                ]);
            }
        }

        $subjects = Subject::all();
        foreach ($streamIds as $streamId) {
            foreach ($subjects as $subject) {
                DB::table('subject_stream')->insert([
                    'subject_id' => $subject->id,
                    'stream_id' => $streamId,
                    'coefficient' => $this->coefficientFor($subject->name),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $students = Student::where('active', true)->get();
        $enrolCount = 0;
        foreach ($students as $student) {
            $matchingStream = Stream::where('class_id', $student->class_id)->first();
            if ($matchingStream) {
                $exists = DB::table('student_stream')
                    ->where('student_id', $student->id)
                    ->where('stream_id', $matchingStream->id)
                    ->exists();
                if (! $exists) {
                    DB::table('student_stream')->insert([
                        'student_id' => $student->id,
                        'stream_id' => $matchingStream->id,
                        'academic_year_id' => $year->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $enrolCount++;
                }
            }
        }

        $subCount = 0;
        foreach ($students as $student) {
            $stream = DB::table('student_stream')->where('student_id', $student->id)->first();
            if (! $stream) continue;
            $streamSubjects = DB::table('subject_stream')->where('stream_id', $stream->stream_id)->pluck('subject_id');
            foreach ($streamSubjects as $subjectId) {
                $exists = DB::table('student_subject')
                    ->where('student_id', $student->id)
                    ->where('subject_id', $subjectId)
                    ->exists();
                if (! $exists) {
                    DB::table('student_subject')->insert([
                        'student_id' => $student->id,
                        'subject_id' => $subjectId,
                        'stream_id' => $stream->stream_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $subCount++;
                }
            }
        }

        $teacher = User::where('email', 'ngufor.calvin@pssnkwen.local')->first();
        if ($teacher) {
            $mathBioSubjects = Subject::whereIn('name', ['Mathematics', 'Biology'])->get();
            $targetStreams = Stream::whereIn('name', ['Form 4', 'Form 5'])->get();
            $assignCount = 0;
            foreach ($targetStreams as $stream) {
                foreach ($mathBioSubjects as $subject) {
                    $exists = DB::table('teacher_assignments')
                        ->where('teacher_id', $teacher->id)
                        ->where('subject_id', $subject->id)
                        ->where('stream_id', $stream->id)
                        ->exists();
                    if (! $exists) {
                        DB::table('teacher_assignments')->insert([
                            'teacher_id' => $teacher->id,
                            'subject_id' => $subject->id,
                            'stream_id' => $stream->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $assignCount++;
                    }
                }
            }
        }

        $classMaster = User::where('email', 'songhi.kingsley@pssnkwen.local')->first();
        if ($classMaster) {
            $form3Stream = Stream::where('name', 'Form 3')->first();
            if ($form3Stream) {
                $exists = DB::table('class_masters')
                    ->where('teacher_id', $classMaster->id)
                    ->where('stream_id', $form3Stream->id)
                    ->exists();
                if (! $exists) {
                    DB::table('class_masters')->insert([
                        'teacher_id' => $classMaster->id,
                        'stream_id' => $form3Stream->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('[academic_core] 1 year, 1 section, ' . count($streamIds)
            . ' streams, 3 terms, 6 tests, ' . $enrolCount
            . ' enrolments, ' . ($subCount > 0 ? "$subCount student subjects" : 'student subjects skipped')
            . ', ' . ($assignCount ?? 0) . ' teacher assignments, 1 class master.');
    }

    private function seedAdmin(): void
    {
        $exists = User::where('email', 'admin@pssnkwen.local')->exists();
        if ($exists) {
            $this->command->info('[admin] school_admin user already exists — skipped.');
            return;
        }

        $admin = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'School Admin',
            'email' => 'admin@pssnkwen.local',
            'password' => Hash::make('secret'),
            'active' => true,
        ]);

        $admin->assignRole('school_admin');

        $this->command->info("[admin] school_admin user created (admin@pssnkwen.local / secret).");
    }

    private function seedGradeRules(): void
    {
        if (DB::table('grade_rules')->count() > 0) {
            $this->command->info('[grade_rules] already seeded — skipped.');
            return;
        }

        $rules = [
            ['grade' => 'A', 'point' => 4.0, 'min_score' => 16.0, 'max_score' => 20.0, 'remark' => 'Excellent'],
            ['grade' => 'B', 'point' => 3.0, 'min_score' => 14.0, 'max_score' => 15.99, 'remark' => 'Very Good'],
            ['grade' => 'C', 'point' => 2.0, 'min_score' => 12.0, 'max_score' => 13.99, 'remark' => 'Good'],
            ['grade' => 'D', 'point' => 1.5, 'min_score' => 10.0, 'max_score' => 11.99, 'remark' => 'Average'],
            ['grade' => 'E', 'point' => 1.0, 'min_score' => 8.0, 'max_score' => 9.99, 'remark' => 'Weak'],
            ['grade' => 'F', 'point' => 0.0, 'min_score' => 0.0, 'max_score' => 7.99, 'remark' => 'Fail'],
        ];

        foreach ($rules as $rule) {
            DB::table('grade_rules')->insert([
                'id' => (string) Uuid::uuid7(),
                'grade' => $rule['grade'],
                'point' => $rule['point'],
                'min_score' => $rule['min_score'],
                'max_score' => $rule['max_score'],
                'remark' => $rule['remark'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('[grade_rules] 6 Cameroon /20 bands seeded.');
    }

    /** Typical Cameroon secondary subject coefficients (weight in the average). */
    private function coefficientFor(string $subjectName): int
    {
        $n = strtolower($subjectName);
        $map = [
            'further' => 4, 'math' => 4, 'physics' => 4, 'chemistry' => 4, 'biology' => 4,
            'english' => 3, 'french' => 3, 'literature' => 3, 'economics' => 3, 'computer' => 3,
            'geography' => 2, 'history' => 2, 'commerce' => 2, 'accounting' => 3, 'religious' => 2,
            'citizen' => 1, 'physical' => 1, 'art' => 1,
        ];
        foreach ($map as $kw => $coeff) {
            if (str_contains($n, $kw)) {
                return $coeff;
            }
        }
        return 2;
    }
}
