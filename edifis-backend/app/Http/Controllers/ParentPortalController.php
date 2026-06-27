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

        // Coefficient-weighted average out of 20 — matches the official term_results,
        // unlike a raw score/max ratio which ignores subject weighting.
        $weighted = 0.0;
        $coefSum = 0.0;
        foreach ($marks as $m) {
            if ($m->max_score > 0 && $m->coefficient > 0) {
                $weighted += ($m->score / $m->max_score) * 20 * $m->coefficient;
                $coefSum += $m->coefficient;
            }
        }
        $avg = $coefSum > 0 ? round($weighted / $coefSum, 2) : 0;

        // The school's official computed result per term (what report cards show).
        $terms = \Illuminate\Support\Facades\DB::table('term_results')
            ->join('terms', 'term_results.term_id', '=', 'terms.id')
            ->where('term_results.student_id', $studentId)
            ->orderBy('terms.position')
            ->selectRaw('terms.name as term, term_results.overall_average as average, term_results.grade as grade, term_results.position as rank')
            ->get()
            ->map(fn ($r) => [
                'term' => $r->term,
                'average' => (float) $r->average,
                'grade' => $r->grade,
                'rank' => (int) $r->rank,
            ]);

        return response()->json([
            'marks' => $marks,
            'average' => $avg,
            'total_score' => $marks->sum('score'),
            'total_max' => $marks->sum('max_score'),
            'terms' => $terms,
        ]);
    }

    public function childTrend(Request $request, string $studentId): JsonResponse
    {
        abort_unless($request->user()->ownsStudent($studentId), 403, 'Not your child.');

        $points = \Illuminate\Support\Facades\DB::table('term_results')
            ->join('terms', 'term_results.term_id', '=', 'terms.id')
            ->where('term_results.student_id', $studentId)
            ->orderBy('terms.position')
            ->selectRaw('terms.name as term, term_results.overall_average as average, term_results.grade as grade, term_results.position as rank')
            ->get()
            ->map(fn ($r) => [
                'term' => $r->term,
                'average' => (float) $r->average,
                'grade' => $r->grade,
                'rank' => (int) $r->rank,
            ]);

        return response()->json(['points' => $points]);
    }

    public function childFees(Request $request, string $studentId, BalanceQuery $balance): JsonResponse
    {
        abort_unless($request->user()->ownsStudent($studentId), 403, 'Not your child.');

        $items = \App\Domain\Ledger\Models\LedgerEntry::where('student_id', $studentId)
            ->orderByDesc('posted_at')
            ->get()
            ->map(fn ($e) => [
                'label' => $e->description ?: ($e->amount >= 0 ? 'Charge' : 'Payment received'),
                'amount' => (int) $e->amount,
                'type' => $e->amount >= 0 ? 'charge' : 'payment',
                'date' => optional($e->posted_at)->toDateString(),
            ]);

        $data = $balance->get($studentId);

        return response()->json([
            'balance' => $data['balance'],
            'currency' => $data['currency'],
            'items' => $items,
        ]);
    }

    public function childAttendance(Request $request, string $studentId): JsonResponse
    {
        abort_unless($request->user()->ownsStudent($studentId), 403, 'Not your child.');

        $counts = AttendanceEvent::where('student_id', $studentId)
            ->whereIn('status', ['present', 'absent', 'late', 'excused'])
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $total = (int) $counts->sum();
        $rate = $total > 0 ? (int) round($present / $total * 100) : null;

        return response()->json([
            'present' => $present,
            'absent' => (int) ($counts['absent'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'excused' => (int) ($counts['excused'] ?? 0),
            'total' => $total,
            'rate' => $rate,
            'attendance_events' => $present, // backward-compatible
        ]);
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
