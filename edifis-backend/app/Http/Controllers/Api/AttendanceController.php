<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Academics\Models\AcademicYear;
use App\Domain\Academics\Models\Stream;
use App\Domain\Attendance\Actions\OpenSession;
use App\Domain\Attendance\Actions\RecordRollCall;
use App\Domain\Attendance\Actions\RecordScan;
use App\Domain\Attendance\Actions\CloseSession;
use App\Domain\Attendance\Actions\VoidScan;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Attendance\Queries\SessionTally;
use App\Domain\Notifications\Notifications\StudentAbsent;
use App\Domain\Students\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController
{
    /** Sentinel "section" id for the all-boarders dorm/night roll call. */
    private const BOARDERS = '__boarders__';

    /** Sections (streams) the user can take attendance for, plus the boarders group. */
    public function sections(): JsonResponse
    {
        $year = AcademicYear::where('is_current', true)->first();

        $streams = Stream::query()
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->where('active', true)
            ->with('schoolClass')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'class' => $s->schoolClass?->name,
            ]);

        // Virtual group: all boarders, for the dorm/night roll call.
        $boarders = [[
            'id' => self::BOARDERS,
            'name' => 'Boarders — night roll call',
            'class' => 'Boarding',
        ]];

        return response()->json(array_merge($boarders, $streams->all()));
    }

    /** The roll-call sheet: roster + any marks already recorded for that day/period. */
    public function rollCallSheet(Request $request): JsonResponse
    {
        $v = $request->validate([
            'stream_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'period' => ['nullable', 'in:AM,PM,FULL'],
        ]);
        $period = $v['period'] ?? 'FULL';
        $boarding = $v['stream_id'] === self::BOARDERS;

        $session = AttendanceSession::where($boarding
            ? ['attendance_date' => $v['date'], 'period' => $period, 'mode' => 'boarding']
            : ['stream_id' => $v['stream_id'], 'attendance_date' => $v['date'], 'period' => $period, 'mode' => 'rollcall']
        )->first();

        $existing = $session ? $session->events()->get()->keyBy('student_id') : collect();

        $students = ($boarding
            ? Student::where('boarding_status', 'boarding')
            : Student::where('stream_id', $v['stream_id']))
            ->where('active', true)
            ->orderBy('family_name')->orderBy('given_name')
            ->get(['id', 'given_name', 'family_name'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => trim($s->family_name . ' ' . $s->given_name),
                'status' => $existing[$s->id]->status ?? null,
                'reason' => $existing[$s->id]->reason ?? null,
            ]);

        return response()->json([
            'session_id' => $session?->id,
            'period' => $period,
            'students' => $students,
        ]);
    }

    /** Submit a daily roll call for a section. */
    public function rollCall(Request $request, RecordRollCall $action): JsonResponse
    {
        $v = $request->validate([
            'stream_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'period' => ['nullable', 'in:AM,PM,FULL'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'uuid'],
            'entries.*.status' => ['required', 'in:present,absent,late,excused'],
            'entries.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $session = $action->handle(
            $v['stream_id'],
            $v['date'],
            $v['period'] ?? 'FULL',
            $request->user()->id,
            $v['entries'],
        );

        $this->alertParentsOfAbsences($action->newlyAbsent, $v['entries'], $v['date']);

        return response()->json([
            'session_id' => $session->id,
            'date' => $v['date'],
            'period' => $session->period,
            'marked' => $session->events->count(),
            'present' => $session->events->where('status', 'present')->count(),
            'absent' => $session->events->where('status', 'absent')->count(),
            'late' => $session->events->where('status', 'late')->count(),
            'excused' => $session->events->where('status', 'excused')->count(),
        ], 201);
    }

    /** Push an alert to the parents of each newly-absent student (fail-soft). */
    private function alertParentsOfAbsences(array $studentIds, array $entries, string $date): void
    {
        if (empty($studentIds)) {
            return;
        }

        $reasons = collect($entries)->keyBy('student_id');

        foreach ($studentIds as $studentId) {
            try {
                $student = Student::find($studentId);
                if (! $student) {
                    continue;
                }
                $name = trim($student->given_name . ' ' . $student->family_name);
                $reason = $reasons[$studentId]['reason'] ?? null;

                $parentIds = DB::table('guardian_student')->where('student_id', $studentId)->pluck('guardian_id');
                foreach ($parentIds as $parentId) {
                    User::find($parentId)?->notify(new StudentAbsent($studentId, $name, $date, $reason));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Principal/VP: who is absent across the school on a given day. */
    public function absentees(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? now()->toDateString();

        $rows = DB::table('attendance_events as e')
            ->join('attendance_sessions as s', 's.id', '=', 'e.session_id')
            ->join('students as st', 'st.id', '=', 'e.student_id')
            ->leftJoin('streams as str', 'str.id', '=', 's.stream_id')
            ->where('s.attendance_date', $date)
            ->where('s.mode', 'rollcall')
            ->whereIn('e.status', ['absent', 'late', 'excused'])
            ->orderBy('str.name')
            ->orderBy('st.family_name')
            ->selectRaw("st.id, trim(st.family_name || ' ' || st.given_name) as name, str.name as section, e.status, e.reason")
            ->get();

        return response()->json([
            'date' => $date,
            'absent' => $rows->where('status', 'absent')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'excused' => $rows->where('status', 'excused')->count(),
            'students' => $rows->values(),
        ]);
    }

    public function openSession(Request $request, OpenSession $action): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'uuid'],
            'subject_id' => ['required', 'uuid'],
            'period' => ['required', 'string'],
        ]);

        $session = $action->handle(
            classId: $validated['class_id'],
            subjectId: $validated['subject_id'],
            period: $validated['period'],
            teacherId: $request->user()->id,
        );

        return response()->json($session, 201);
    }

    public function scan(string $sessionId, Request $request, RecordScan $action): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'uuid'],
            'source' => ['nullable', 'in:qr_scan,manual_override'],
            'void_reason' => ['nullable', 'string'],
        ]);

        $result = $action->handle(
            sessionId: $sessionId,
            studentId: $validated['student_id'],
            source: $validated['source'] ?? 'qr_scan',
            voidReason: $validated['void_reason'] ?? null,
            deviceId: $request->input('device_id'),
            scannedBy: $request->user()->id,
        );

        if (($result['status'] ?? null) === 'replay') {
            return response()->json([
                'code' => 'idempotency_replay',
                'message' => 'This scan was already recorded.',
                'details' => null,
                'retry_after_seconds' => null,
            ], 200);
        }

        return response()->json($result, 201);
    }

    public function closeSession(string $sessionId, CloseSession $action): JsonResponse
    {
        $session = $action->handle($sessionId);
        return response()->json($session);
    }

    public function voidScan(Request $request, VoidScan $action): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'uuid'],
            'reason' => ['required', 'string'],
        ]);

        $voidEvent = $action->handle(
            eventId: $validated['event_id'],
            reason: $validated['reason'],
            actorId: $request->user()->id,
        );

        return response()->json($voidEvent, 201);
    }

    public function tally(string $sessionId, SessionTally $query): JsonResponse
    {
        return response()->json($query->get($sessionId));
    }
}
