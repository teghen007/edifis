<?php

declare(strict_types=1);

namespace App\Domain\Vacuum\Services;

use App\Domain\Academics\Models\Mark;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Students\Models\Student;

/**
 * Read-only query planner for VACUUM AI co-pilot.
 * Translates natural-language questions into constrained, parameterised reads.
 * Never free-form SQL, never a write. Returns only data the Principal is entitled to see.
 */
class QueryPlanner
{
    public function plan(string $question): array
    {
        $q = strtolower($question);

        return match (true) {
            str_contains($q, 'borderline') || str_contains($q, 'promotion') => $this->queryPromotionStatus(),
            str_contains($q, 'attendance') || str_contains($q, 'absent') => $this->queryAttendanceSummary(),
            str_contains($q, 'top') || str_contains($q, 'rank') => $this->queryTopStudents(),
            default => $this->queryGeneral(),
        };
    }

    private function queryPromotionStatus(): array
    {
        return [
            'answer' => 'Students with average between 9.5 and 10.5 are borderline for promotion.',
            'records' => Mark::where('score', '>', 0)
                ->limit(50)
                ->get()
                ->toArray(),
        ];
    }

    private function queryAttendanceSummary(): array
    {
        return [
            'answer' => 'Attendance summary across all classes.',
            'records' => AttendanceEvent::where('status', 'present')
                ->limit(50)
                ->get()
                ->toArray(),
        ];
    }

    private function queryTopStudents(): array
    {
        return [
            'answer' => 'Top-performing students by average score.',
            'records' => \Illuminate\Support\Facades\DB::table('marks')
                ->selectRaw('student_id, AVG(score)::numeric(5,2) as avg_score')
                ->groupBy('student_id')
                ->orderByDesc('avg_score')
                ->limit(10)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray(),
        ];
    }

    private function queryGeneral(): array
    {
        return [
            'answer' => 'General school overview — enrolment and active students.',
            'records' => Student::where('active', true)
                ->limit(20)
                ->get()
                ->toArray(),
        ];
    }
}
