<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Promotion\Models\PromotionDecision;
use App\Domain\Promotion\Models\PromotionRuleset;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * End-of-year council deliberation for a stream: compute each student's yearly
 * average from the already-computed term results (which are coefficient-weighted)
 * and propose an outcome — Promoted / Conditional / Repeat. Idempotent (upserts).
 */
class DeliberateStream
{
    public function handle(string $streamId, string $academicYearId): array
    {
        $ruleset = PromotionRuleset::where('active', true)->first()
            ?? PromotionRuleset::create([
                'version' => 'default-v1',
                'baseline' => 10.0,
                'coefficients' => [],
                'active' => true,
            ]);

        $baseline = (float) $ruleset->baseline;
        $conditionalFloor = $baseline - 2.0; // [floor, baseline) = conditional

        $termIds = DB::table('terms')->where('academic_year_id', $academicYearId)->pluck('id');
        if ($termIds->isEmpty()) {
            return ['deliberated' => 0, 'students' => []];
        }

        $studentIds = DB::table('student_stream')->where('stream_id', $streamId)->pluck('student_id');

        $rows = [];
        foreach ($studentIds as $studentId) {
            $avgs = DB::table('term_results')
                ->where('student_id', $studentId)
                ->whereIn('term_id', $termIds)
                ->pluck('overall_average');

            if ($avgs->isEmpty()) {
                continue;
            }

            $yearly = round($avgs->avg(), 2);
            $outcome = $yearly >= $baseline ? 'promoted'
                : ($yearly >= $conditionalFloor ? 'conditional' : 'repeat');

            $decision = PromotionDecision::updateOrCreate(
                ['student_id' => $studentId, 'academic_year' => $academicYearId],
                [
                    'id' => (string) Uuid::uuid7(),
                    'yearly_average' => $yearly,
                    'outcome' => $outcome,
                    'ruleset_version' => $ruleset->version,
                    'computed_at' => now(),
                ]
            );

            $student = DB::table('students')->where('id', $studentId)->first();
            $rows[] = [
                'decision_id' => $decision->id,
                'student_id' => $studentId,
                'name' => trim(($student->given_name ?? '') . ' ' . ($student->family_name ?? '')),
                'yearly_average' => $yearly,
                'outcome' => $outcome,
            ];
        }

        usort($rows, fn ($a, $b) => $b['yearly_average'] <=> $a['yearly_average']);

        return ['deliberated' => count($rows), 'baseline' => $baseline, 'students' => $rows];
    }
}
