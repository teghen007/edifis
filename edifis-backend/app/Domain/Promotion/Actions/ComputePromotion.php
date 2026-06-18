<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Academics\Models\Mark;
use App\Domain\Promotion\Models\PromotionDecision;
use App\Domain\Promotion\Models\PromotionRuleset;

class ComputePromotion
{
    /**
     * Compute promotion outcome for a student.
     * White paper §6 — coefficient weighting, baseline boundary, ruleset versioning.
     *
     * @param string[] $sequences Explicit sequence identifiers (e.g. ['2026-T1-Seq1', '2026-T1-Seq2', '2026-T2-Seq1']).
     *        The academic_year still identifies the ruleset era.
     */
    public function handle(string $studentId, string $academicYear, array $sequences, string $pathway = 'general'): PromotionDecision
    {
        $ruleset = PromotionRuleset::where('active', true)->firstOrFail();
        $coefficients = $ruleset->coefficients ?? [];
        $baseline = $ruleset->baseline;

        $marks = Mark::where('student_id', $studentId)
            ->whereIn('sequence', $sequences)
            ->get();

        if ($marks->isEmpty()) {
            throw new \RuntimeException("No marks found for student {$studentId} in {$academicYear}");
        }

        $weightedScores = $marks->map(function (Mark $mark) use ($coefficients) {
            $coeff = $coefficients[$mark->subject_id] ?? 1.0;
            $termAvg = $mark->score / $mark->max_score * 20; // normalize to out-of-20
            return $termAvg * $coeff;
        });

        $totalCoef = $marks->map(fn (Mark $mark) => $coefficients[$mark->subject_id] ?? 1.0)->sum();
        $yearlyAverage = $totalCoef > 0 ? $weightedScores->sum() / $totalCoef : 0;

        $outcome = $yearlyAverage >= $baseline ? 'advance' : 'repeat';

        return PromotionDecision::create([
            'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
            'student_id' => $studentId,
            'academic_year' => $academicYear,
            'yearly_average' => round($yearlyAverage, 2),
            'outcome' => $outcome,
            'ruleset_version' => $ruleset->version,
            'pathway' => $pathway,
            'computed_at' => now(),
        ]);
    }
}
