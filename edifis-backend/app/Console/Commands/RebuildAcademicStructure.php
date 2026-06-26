<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Academics\Models\AcademicYear;
use App\Domain\Academics\Models\SchoolClass;
use App\Domain\Academics\Models\Section;
use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Subject;
use App\Domain\Students\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Repairs the academic structure into one consistent shape:
 *   School Class (Form 1) -> Section streams (Form 1 A, Form 1 B) -> Students,
 * with a class-scoped subject catalogue (class_subject) carrying class-specific
 * codes (GEO 1, GEO US) that cascades to each section's subject_stream.
 *
 * Idempotent: safe to run repeatedly.
 */
class RebuildAcademicStructure extends Command
{
    protected $signature = 'edifis:rebuild-structure';

    protected $description = 'Repair classes/sections/student enrolment + class-scoped subjects';

    public function handle(): int
    {
        DB::transaction(function () {
            $year = AcademicYear::where('is_current', true)->firstOrFail();
            $section = Section::first();
            $classes = SchoolClass::orderBy('level')->get();

            // 1. One "A" section stream per class (reuse the existing per-form stream).
            $streams = []; // [classId][letter] => Stream
            foreach ($classes as $class) {
                $existing = Stream::where('class_id', $class->id)
                    ->where('academic_year_id', $year->id)
                    ->orderBy('name')->first();

                $streams[$class->id]['A'] = $existing
                    ? tap($existing)->update(['name' => $class->name . ' A'])
                    : Stream::create([
                        'name' => $class->name . ' A',
                        'class_id' => $class->id,
                        'section_id' => $section?->id,
                        'academic_year_id' => $year->id,
                        'active' => true,
                    ]);
            }

            // 2. Demo has a Form 1 B section too.
            $form1 = $classes->firstWhere('name', 'Form 1');
            $form2 = $classes->firstWhere('name', 'Form 2');
            if ($form1) {
                $streams[$form1->id]['B'] = Stream::firstOrCreate(
                    ['class_id' => $form1->id, 'academic_year_id' => $year->id, 'name' => 'Form 1 B'],
                    ['section_id' => $section?->id, 'active' => true],
                );
            }

            // 3. Assign every active student to exactly one section, derived from the
            //    legacy current_class_id grouping (f1a / f1b / f2a / strays).
            $assign = function (Student $s, SchoolClass $class, Stream $stream): void {
                $s->update([
                    'class_id' => $class->id,
                    'current_class_id' => $class->id,
                    'stream_id' => $stream->id,
                ]);
                DB::table('student_stream')->where('student_id', $s->id)->delete();
                DB::table('student_stream')->insert([
                    'student_id' => $s->id,
                    'stream_id' => $stream->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            };

            foreach (Student::where('active', true)->get() as $s) {
                $cc = (string) $s->current_class_id;
                if (str_starts_with($cc, 'f1b') && isset($streams[$form1->id]['B'])) {
                    $assign($s, $form1, $streams[$form1->id]['B']);
                } elseif (str_starts_with($cc, 'f2') && $form2) {
                    $assign($s, $form2, $streams[$form2->id]['A']);
                } elseif ($form1) {
                    $assign($s, $form1, $streams[$form1->id]['A']);
                }
            }

            // 4. Class-scoped subject catalogue with class-specific codes + cascade.
            $shortLabel = fn (SchoolClass $c): string => match ($c->name) {
                'Lower Sixth' => 'LS',
                'Upper Sixth' => 'US',
                default => (string) $c->level,
            };

            foreach ($classes as $class) {
                $primary = $streams[$class->id]['A'];
                $subjectIds = DB::table('subject_stream')->where('stream_id', $primary->id)->pluck('subject_id');
                if ($subjectIds->isEmpty()) {
                    $subjectIds = Subject::where('active', true)->pluck('id');
                }

                foreach (Subject::whereIn('id', $subjectIds)->get() as $subject) {
                    $code = trim($subject->code . ' ' . $shortLabel($class));
                    $existing = DB::table('class_subject')
                        ->where('class_id', $class->id)->where('subject_id', $subject->id)->first();

                    if ($existing) {
                        DB::table('class_subject')->where('id', $existing->id)->update([
                            'code' => $code,
                            'coefficient' => $subject->coefficient ?? 1,
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('class_subject')->insert([
                            'id' => (string) Str::uuid(),
                            'class_id' => $class->id,
                            'subject_id' => $subject->id,
                            'code' => $code,
                            'coefficient' => $subject->coefficient ?? 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // cascade to every section (stream) of this class
                foreach ($streams[$class->id] as $stream) {
                    foreach ($subjectIds as $sid) {
                        DB::table('subject_stream')->updateOrInsert(
                            ['stream_id' => $stream->id, 'subject_id' => $sid],
                            ['updated_at' => now(), 'created_at' => now()],
                        );
                    }
                }
            }
        });

        $this->info('Academic structure rebuilt.');
        $this->table(
            ['Section', 'Class', 'Students'],
            DB::table('streams as st')
                ->leftJoin('school_classes as c', 'c.id', '=', 'st.class_id')
                ->leftJoin('students as s', 's.stream_id', '=', 'st.id')
                ->where('s.active', true)
                ->groupBy('st.name', 'c.name')
                ->selectRaw('st.name as section, c.name as class, count(s.id) as students')
                ->orderBy('c.name')->get()
                ->map(fn ($r) => [$r->section, $r->class, $r->students])->all(),
        );

        return self::SUCCESS;
    }
}
