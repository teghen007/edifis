<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\AttendanceSession;
use Ramsey\Uuid\Uuid;

class OpenSession
{
    public function handle(string $classId, string $subjectId, string $period, string $teacherId): AttendanceSession
    {
        return AttendanceSession::create([
            'id' => (string) Uuid::uuid7(),
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'period' => $period,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }
}
