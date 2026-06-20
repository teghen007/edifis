<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Academics\Actions\RecordMark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class MarksController
{
    public function store(Request $request, RecordMark $recordMark): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'uuid'],
            'revision' => ['required', 'string'],
            'revision_parent' => ['nullable', 'string'],
            'student_id' => ['required', 'uuid'],
            'subject_id' => ['required', 'uuid'],
            'class_id' => ['required', 'uuid'],
            'sequence' => ['required', 'string'],
            'owner_teacher_id' => ['nullable', 'uuid'],
            'score' => ['required', 'numeric', 'min:0'],
            'max_score' => ['required', 'numeric', 'min:1'],
            'coefficient' => ['nullable', 'numeric', 'min:0'],
            'published' => ['boolean'],
        ]);

        $mark = $recordMark->handle(
            id: $validated['id'],
            revision: $validated['revision'],
            revisionParent: $validated['revision_parent'] ?? null,
            studentId: $validated['student_id'],
            subjectId: $validated['subject_id'],
            classId: $validated['class_id'],
            sequence: $validated['sequence'],
            ownerTeacherId: $request->user()->id,
            score: (float) $validated['score'],
            maxScore: (float) $validated['max_score'],
            coefficient: isset($validated['coefficient']) ? (float) $validated['coefficient'] : null,
            published: $validated['published'] ?? false,
        );

        return response()->json($mark, 201);
    }
}
