<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Academics\Models\Mark;
use App\Domain\Students\Models\Student;

class RenderReportCard
{
    /**
     * Render a student report card as an array (PDF rendering via DOMPDF in full impl).
     * Phase 4 skeleton: returns structured data; Phase 5 adds DOMPDF template.
     */
    public function handle(string $studentId, string $sequence): array
    {
        $student = Student::findOrFail($studentId);
        $marks = Mark::where('student_id', $studentId)
            ->where('sequence', $sequence)
            ->get();

        $totalScore = $marks->sum('score');
        $totalMax = $marks->sum('max_score');
        $average = $totalMax > 0 ? round(($totalScore / $totalMax) * 20, 2) : 0;

        return [
            'student' => [
                'id' => $student->id,
                'name' => "{$student->given_name} {$student->family_name}",
                'master_pea_id' => $student->master_pea_id,
            ],
            'sequence' => $sequence,
            'marks' => $marks->toArray(),
            'total_score' => $totalScore,
            'total_max' => $totalMax,
            'average' => $average,
        ];
    }
}
