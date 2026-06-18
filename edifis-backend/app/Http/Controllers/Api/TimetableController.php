<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Timetable\Actions\UpsertTimetableEntry;
use App\Domain\Timetable\Actions\ApproveTimetable;
use App\Domain\Timetable\Actions\UpsertCalendarEvent;
use App\Domain\Timetable\Models\TimetableEntry;
use App\Domain\Timetable\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController
{
    public function store(Request $request, UpsertTimetableEntry $upsert): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'uuid'],
            'class_id' => ['required', 'uuid'],
            'subject_id' => ['required', 'uuid'],
            'teacher_id' => ['required', 'uuid'],
            'day_of_week' => ['required', 'string'],
            'period_start' => ['required', 'string'],
            'period_end' => ['required', 'string'],
            'room' => ['nullable', 'string'],
        ]);

        $entry = $upsert->handle(
            id: $validated['id'] ?? null,
            classId: $validated['class_id'],
            subjectId: $validated['subject_id'],
            teacherId: $validated['teacher_id'],
            dayOfWeek: $validated['day_of_week'],
            periodStart: $validated['period_start'],
            periodEnd: $validated['period_end'],
            authorId: $request->user()->id,
            room: $validated['room'] ?? null,
        );

        return response()->json($entry, 201);
    }

    public function approve(string $entryId, Request $request, ApproveTimetable $approve): JsonResponse
    {
        $entry = $approve->handle($entryId, $request->user()->id);

        return response()->json($entry);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = TimetableEntry::query();

        if ($user->hasRole('subject_teacher')) {
            $query->where('teacher_id', $user->id);
        } elseif ($user->hasRole('class_master')) {
            $query->where('class_id', $request->query('class_id'));
        } elseif ($user->hasRole('parent')) {
            $query->where('class_id', $request->query('class_id'));
        }

        return response()->json($query->get());
    }

    public function calendar(Request $request, UpsertCalendarEvent $upsert): JsonResponse
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'id' => ['nullable', 'uuid'],
                'title' => ['required', 'string'],
                'type' => ['required', 'string'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['required', 'date'],
                'scope' => ['nullable', 'string'],
                'class_id' => ['nullable', 'uuid'],
            ]);

            $event = $upsert->handle(
                id: $validated['id'] ?? null,
                title: $validated['title'],
                type: $validated['type'],
                startsAt: $validated['starts_at'],
                endsAt: $validated['ends_at'],
                authorId: $request->user()->id,
                scope: $validated['scope'] ?? 'school',
                classId: $validated['class_id'] ?? null,
            );

            return response()->json($event, 201);
        }

        return response()->json(CalendarEvent::all());
    }
}
