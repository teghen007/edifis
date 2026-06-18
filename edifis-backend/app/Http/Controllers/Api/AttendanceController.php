<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Attendance\Actions\OpenSession;
use App\Domain\Attendance\Actions\RecordScan;
use App\Domain\Attendance\Actions\CloseSession;
use App\Domain\Attendance\Actions\VoidScan;
use App\Domain\Attendance\Queries\SessionTally;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController
{
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
