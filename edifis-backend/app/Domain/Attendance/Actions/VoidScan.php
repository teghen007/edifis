<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Audit\Services\AuditLogger;
use Ramsey\Uuid\Uuid;

class VoidScan
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(string $eventId, string $reason, string $actorId): AttendanceEvent
    {
        $original = AttendanceEvent::findOrFail($eventId);

        $voidEvent = AttendanceEvent::create([
            'id' => (string) Uuid::uuid7(),
            'revision' => ($original->revision ?? '') . '-voided',
            'session_id' => $original->session_id,
            'student_id' => $original->student_id,
            'scanned_at' => now(),
            'device_id' => $original->device_id,
            'scanned_by' => $original->scanned_by,
            'source' => $original->source,
            'status' => 'void',
            'void_reason' => $reason,
        ]);

        $this->audit->log(
            actorId: $actorId,
            action: 'attendance.void',
            entityType: 'attendance_event',
            entityId: $voidEvent->id,
            before: ['original_event_id' => $eventId],
            after: $voidEvent->toArray(),
        );

        return $voidEvent;
    }
}
