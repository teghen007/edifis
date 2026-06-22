<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Conduct\Models\ConductRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConductController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
            'stream_id' => ['nullable', 'uuid'],
            'conduct_grade' => ['required', 'string', 'in:Excellent,Good,Fair,Poor'],
            'punctuality' => ['nullable', 'string', 'max:50'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $record = ConductRecord::updateOrCreate(
            ['student_id' => $validated['student_id'], 'term_id' => $validated['term_id']],
            [
                'stream_id' => $validated['stream_id'] ?? null,
                'conduct_grade' => $validated['conduct_grade'],
                'punctuality' => $validated['punctuality'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'recorded_by' => $request->user()->id,
            ]
        );

        return response()->json($record);
    }

    public function index(Request $request): JsonResponse
    {
        $streamId = $request->query('stream_id');
        $termId = $request->query('term_id');

        abort_unless($streamId && $termId, 400, 'Missing stream_id or term_id');

        $rows = DB::table('conduct_records')
            ->join('students', 'conduct_records.student_id', '=', 'students.id')
            ->where('conduct_records.stream_id', $streamId)
            ->where('conduct_records.term_id', $termId)
            ->select(
                'conduct_records.student_id',
                DB::raw("trim(students.given_name || ' ' || students.family_name) as name"),
                'conduct_records.conduct_grade',
                'conduct_records.punctuality',
                'conduct_records.comment'
            )
            ->orderBy('name')
            ->get();

        return response()->json($rows);
    }
}
