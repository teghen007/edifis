<?php

declare(strict_types=1);

namespace App\Domain\Results\Actions;

use App\Domain\Academics\Models\Mark;
use App\Domain\Academics\Models\Term;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class ComputeResults
{
    public function handle(string $streamId, string $termId): array
    {
        $term = Term::with('tests')->findOrFail($termId);
        $testNames = $term->tests->pluck('name')->all();

        $position = $term->position ?? 1;
        $legacyPatterns = [];
        foreach ($term->tests as $test) {
            $legacyPatterns[] = '2026-T' . $position . '-Seq' . $test->position;
        }
        $allPatterns = array_merge($testNames, $legacyPatterns);

        $students = DB::table('student_stream')
            ->where('stream_id', $streamId)
            ->pluck('student_id');

        $subjectResultsCount = 0;

        foreach ($students as $studentId) {
            $subjects = DB::table('student_subject')
                ->where('student_id', $studentId)
                ->where('stream_id', $streamId)
                ->pluck('subject_id');

            foreach ($subjects as $subjectId) {
                $marks = Mark::where('student_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->whereIn('sequence', $allPatterns)
                    ->get();

                if ($marks->isEmpty()) {
                    continue;
                }

                $normalized = $marks->map(fn ($m) => $m->max_score > 0 ? ($m->score / $m->max_score) * 20 : 0);
                $average = round($normalized->avg(), 2);

                $rule = DB::table('grade_rules')
                    ->where('min_score', '<=', $average)
                    ->where('max_score', '>=', $average)
                    ->first();

                $grade = $rule->grade ?? 'F';
                $point = $rule->point ?? 0.0;

                DB::table('subject_results')->upsert([
                    'id' => (string) Uuid::uuid7(),
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'stream_id' => $streamId,
                    'term_id' => $termId,
                    'average' => $average,
                    'grade' => $grade,
                    'point' => $point,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], ['student_id', 'subject_id', 'term_id']);

                $subjectResultsCount++;
            }
        }

        $studentRows = [];
        foreach ($students as $studentId) {
            $subjResults = DB::table('subject_results')
                ->where('student_id', $studentId)
                ->where('term_id', $termId)
                ->get();

            if ($subjResults->isEmpty()) {
                continue;
            }

            $overallAverage = round($subjResults->avg('average'), 2);
            $totalPoints = $subjResults->sum('point');
            $subjectsCount = $subjResults->count();

            $rule = DB::table('grade_rules')
                ->where('min_score', '<=', $overallAverage)
                ->where('max_score', '>=', $overallAverage)
                ->first();

            $grade = $rule->grade ?? 'F';

            $studentRows[] = [
                'student_id' => $studentId,
                'overall_average' => $overallAverage,
                'total_points' => $totalPoints,
                'subjects_count' => $subjectsCount,
                'grade' => $grade,
            ];
        }

        usort($studentRows, fn ($a, $b) => $b['overall_average'] <=> $a['overall_average']);

        $position = 0;
        $prevAvg = null;
        $termCount = 0;

        foreach ($studentRows as $i => &$row) {
            if ($row['overall_average'] !== $prevAvg) {
                $position = $i + 1;
            }
            $row['position'] = $position;
            $prevAvg = $row['overall_average'];

            DB::table('term_results')->upsert([
                'id' => (string) Uuid::uuid7(),
                'student_id' => $row['student_id'],
                'stream_id' => $streamId,
                'term_id' => $termId,
                'overall_average' => $row['overall_average'],
                'grade' => $row['grade'],
                'total_points' => $row['total_points'],
                'position' => $position,
                'subjects_count' => $row['subjects_count'],
                'created_at' => now(),
                'updated_at' => now(),
            ], ['student_id', 'term_id']);

            $termCount++;
        }

        return [
            'stream_id' => $streamId,
            'term_id' => $termId,
            'subjects_computed' => $subjectResultsCount,
            'students_ranked' => $termCount,
        ];
    }
}
