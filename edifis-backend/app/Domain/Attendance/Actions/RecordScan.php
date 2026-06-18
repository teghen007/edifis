<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Audit\Services\AuditLogger;
use App\Support\Idempotency;
use Ramsey\Uuid\Uuid;

class RecordScan
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        string $sessionId,
        string $studentId,
        string $source,
        ?string $voidReason = null,
        ?string $deviceId = null,
        ?string $scannedBy = null,
    ): mixed {
        $session = AttendanceSession::findOrFail($sessionId);

        if ($source === 'manual_override' && empty($voidReason)) {
            throw new \InvalidArgumentException('manual_override requires void_reason');
        }

        $idempotencyKey = $sessionId . '-' . $studentId;
        $eventId = (string) Uuid::uuid7();
        $revision = $idempotencyKey; // Stable per session+student

        return Idempotency::applyOnce($idempotencyKey, $idempotencyKey, function () use (
            $sessionId, $studentId, $source, $voidReason, $deviceId, $scannedBy, $eventId, $revision
        ) {
            $event = AttendanceEvent::create([
                'id' => $eventId,
                'revision' => $revision,
                'session_id' => $sessionId,
                'student_id' => $studentId,
                'scanned_at' => now(),
                'device_id' => $deviceId,
                'scanned_by' => $scannedBy,
                'source' => $source,
                'status' => 'present',
                'void_reason' => $voidReason,
            ]);

            $this->audit->log(
                actorId: $scannedBy ?? '00000000-0000-0000-0000-000000000001',
                action: 'attendance.scan',
                entityType: 'attendance_event',
                entityId: $eventId,
                after: $event->toArray(),
                deviceId: $deviceId,
            );

            return $event;
        });
    }
}
