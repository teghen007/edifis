<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\Academics\Models\Mark;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Timetable\Models\CalendarEvent;
use App\Domain\Students\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentPortalController
{
    public function children(Request $request): JsonResponse
    {
        // In production: guardian-child relationships via a pivot table.
        // For pilot: return all students (demo). Production would be:
        //   $studentIds = GuardianStudent::where('guardian_id', $request->user()->id)->pluck('student_id');
        return response()->json(
            Student::where('active', true)->limit(20)->get()
        );
    }

    public function childBalance(string $studentId, BalanceQuery $balance): JsonResponse
    {
        return response()->json($balance->get($studentId));
    }

    public function childResults(string $studentId): JsonResponse
    {
        $marks = Mark::where('student_id', $studentId)
            ->where('published', true)
            ->get();

        $total = $marks->sum('score');
        $max = $marks->sum('max_score');
        $avg = $max > 0 ? round(($total / $max) * 20, 2) : 0;

        return response()->json([
            'marks' => $marks,
            'average' => $avg,
            'total_score' => $total,
            'total_max' => $max,
        ]);
    }

    public function childAttendance(string $studentId): JsonResponse
    {
        $events = AttendanceEvent::where('student_id', $studentId)
            ->where('status', 'present')
            ->count();

        return response()->json(['attendance_events' => $events]);
    }

    public function calendar(): JsonResponse
    {
        return response()->json(
            CalendarEvent::where('scope', 'school')
                ->orderBy('starts_at')
                ->limit(50)
                ->get()
        );
    }
}
