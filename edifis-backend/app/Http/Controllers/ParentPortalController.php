<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\AI\Actions\ParentAssistant;
use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\Academics\Models\Mark;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Timetable\Models\CalendarEvent;
use App\Domain\Students\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentPortalController
{
    public function ask(Request $request, ParentAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        try {
            return response()->json($assistant->ask($request->user(), $validated['question']));
        } catch (\Throwable $e) {
            return response()->json([
                'answer' => 'The assistant is unavailable right now. Please try again shortly.',
            ]);
        }
    }

    public function children(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->children()->where('active', true)->get()
        );
    }

    public function childBalance(Request $request, string $studentId, BalanceQuery $balance): JsonResponse
    {
        abort_unless($request->user()->ownsStudent($studentId), 403, 'Not your child.');

        return response()->json($balance->get($studentId));
    }

    public function childResults(Request $request, string $studentId): JsonResponse
    {
        abort_unless($request->user()->ownsStudent($studentId), 403, 'Not your child.');

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

    public function childAttendance(Request $request, string $studentId): JsonResponse
    {
        abort_unless($request->user()->ownsStudent($studentId), 403, 'Not your child.');

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
