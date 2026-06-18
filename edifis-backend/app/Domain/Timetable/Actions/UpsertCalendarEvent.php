<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Actions;

use App\Domain\Timetable\Models\CalendarEvent;
use Ramsey\Uuid\Uuid;

class UpsertCalendarEvent
{
    public function handle(
        ?string $id,
        string $title,
        string $type,
        string $startsAt,
        string $endsAt,
        string $authorId,
        string $scope = 'school',
        ?string $classId = null,
    ): CalendarEvent {
        return CalendarEvent::updateOrCreate(
            ['id' => $id ?? (string) Uuid::uuid7()],
            [
                'title' => $title,
                'type' => $type,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'scope' => $scope,
                'class_id' => $classId,
                'created_by' => $authorId,
            ]
        );
    }
}
