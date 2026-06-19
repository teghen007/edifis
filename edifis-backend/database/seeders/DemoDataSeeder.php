<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academics\Models\Mark;
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

    public function run(): void
    {
        $this->command->info('=== DemoDataSeeder ===');

        $this->seedStudents();
        $this->seedMarks();
        $this->seedAttendance();
        $this->seedFees();
        $this->seedTimetable();
        $this->seedCalendarEvents();
        $this->seedParent();

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
        $existing = Mark::where('sequence', '2026-T1-Seq1')->count();
        if ($existing > count($this->studentIds)) {
            $this->command->info('[marks] already seeded — skipped.');
            return;
        }

        $classIds = array_values(self::CLASS_IDS);
        $teacher = User::where('email', 'ngufor.calvin@pssnkwen.local')->first();
        $teacherId = $teacher?->id ?? '00000000-0000-0000-0000-000000000999';
        $subjects = array_values(self::SUBJECT_IDS);
        $count = 0;

        foreach ($this->studentIds as $i => $studentId) {
            foreach ($subjects as $j => $subjectId) {
                if ($j >= 2) continue;

                $exists = Mark::where('student_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->where('sequence', '2026-T1-Seq1')
                    ->exists();

                if (! $exists) {
                    $score = random_int(8, 20);
                    Mark::create([
                        'id' => (string) Uuid::uuid7(),
                        'revision' => 'r1',
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                        'class_id' => $classIds[$i % count($classIds)],
                        'sequence' => '2026-T1-Seq1',
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
            $this->command->info('[fees] already seeded — skipped.');
            return;
        }

        $items = CatalogueItem::all();
        if ($items->isEmpty()) {
            $this->command->warn('[fees] no catalogue items found — run LabSeeder first.');
            return;
        }

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

    private function seedTimetable(): void
    {
        $existing = TimetableEntry::count();
        if ($existing >= 12) {
            $this->command->info('[timetable] already seeded — skipped.');
            return;
        }

        $teacher = User::where('email', 'ngufor.calvin@pssnkwen.local')->first();
        $teacherId = $teacher?->id ?? '00000000-0000-0000-0000-000000000999';
        $classIds = array_values(self::CLASS_IDS);
        $subjects = array_values(self::SUBJECT_IDS);
        $days = [1, 2, 3, 4, 5];
        $count = 0;

        foreach ($classIds as $ci => $classId) {
            foreach (array_slice($days, 0, 3) as $day) {
                foreach ([['08:00', '09:00'], ['09:00', '10:00'], ['10:30', '11:30']] as $pi => $period) {
                    if ($count >= 12) break 3;

                    $exists = TimetableEntry::where('class_id', $classId)
                        ->where('day_of_week', $day)
                        ->where('period_start', $period[0])
                        ->exists();

                    if (! $exists) {
                        TimetableEntry::create([
                            'id' => (string) Uuid::uuid7(),
                            'class_id' => $classId,
                            'subject_id' => $subjects[($ci + $pi) % count($subjects)],
                            'teacher_id' => $teacherId,
                            'day_of_week' => $day,
                            'period_start' => $period[0],
                            'period_end' => $period[1],
                            'room' => 'Room ' . ($ci + 1),
                            'created_by' => $teacherId,
                            'is_approved' => $count < 9,
                        ]);
                        $count++;
                    }
                }
            }
        }

        $this->command->info("[timetable] {$count} entries seeded.");
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
}
