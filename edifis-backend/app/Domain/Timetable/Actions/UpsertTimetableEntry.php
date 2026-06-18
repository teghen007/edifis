<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Actions;

use App\Domain\Timetable\Models\TimetableEntry;
use Ramsey\Uuid\Uuid;

class UpsertTimetableEntry
{
    public function handle(
        ?string $id,
        string $classId,
        string $subjectId,
        string $teacherId,
        string $dayOfWeek,
        string $periodStart,
        string $periodEnd,
        string $authorId,
        ?string $room = null,
    ): TimetableEntry {
        return TimetableEntry::updateOrCreate(
            ['id' => $id ?? (string) Uuid::uuid7()],
            [
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'day_of_week' => $dayOfWeek,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'room' => $room,
                'created_by' => $authorId,
                'is_approved' => false,
            ]
        );
    }
}
