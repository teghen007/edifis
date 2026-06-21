<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Academics\Actions\RecordMark;
use App\Exports\MarkSheetExport;
use App\Imports\MarkSheetImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
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

    public function template(Request $request)
    {
        $user = $request->user();
        $streamId = $request->query('stream_id');
        $subjectId = $request->query('subject_id');
        $testId = $request->query('test_id');

        abort_unless($user->teachesSubjectInStream($subjectId, $streamId)
            || $user->hasAnyRoleName(['principal', 'vice_principal', 'school_admin']), 403);

        abort_unless($streamId && $subjectId && $testId, 400, 'Missing stream_id, subject_id, or test_id');

        return Excel::download(new MarkSheetExport($streamId, $subjectId, $testId), 'marksheet.xlsx');
    }

    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx']]);

        $import = new MarkSheetImport;
        $rows = Excel::toCollection($import, $request->file('file'))->first();
        $import->collection($rows);

        abort_unless($user->teachesSubjectInStream($import->getSubjectId(), $import->getStreamId())
            || $user->hasAnyRoleName(['principal', 'vice_principal', 'school_admin']), 403);

        $result = $import->ingest($rows, $user->id);

        return response()->json($result);
    }
}
