<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Promotion\Actions\DeliberateStream;
use App\Domain\Promotion\Actions\OverridePromotion;
use App\Domain\Promotion\Models\PromotionDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController
{
    public function deliberate(Request $request, DeliberateStream $deliberate): JsonResponse
    {
        $validated = $request->validate([
            'stream_id' => ['required', 'uuid'],
            'academic_year_id' => ['required', 'uuid'],
        ]);

        return response()->json($deliberate->handle($validated['stream_id'], $validated['academic_year_id']));
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stream_id' => ['required', 'uuid'],
            'academic_year_id' => ['required', 'uuid'],
        ]);

        $studentIds = DB::table('student_stream')->where('stream_id', $validated['stream_id'])->pluck('student_id');

        $rows = PromotionDecision::whereIn('student_id', $studentIds)
            ->where('academic_year', $validated['academic_year_id'])
            ->get()
            ->map(function ($d) {
                $student = DB::table('students')->where('id', $d->student_id)->first();
                return [
                    'decision_id' => $d->id,
                    'student_id' => $d->student_id,
                    'name' => trim(($student->given_name ?? '') . ' ' . ($student->family_name ?? '')),
                    'yearly_average' => $d->yearly_average,
                    'outcome' => $d->outcome,
                ];
            })
            ->sortByDesc('yearly_average')
            ->values();

        return response()->json($rows);
    }

    public function override(Request $request, string $decisionId, OverridePromotion $override): JsonResponse
    {
        $validated = $request->validate([
            'new_outcome' => ['required', 'string', 'in:promoted,conditional,repeat,dismissed'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $override->handle($decisionId, $validated['new_outcome'], $validated['reason'], $request->user()->id);
            // reflect the new outcome on the decision itself for easy reads
            PromotionDecision::where('id', $decisionId)->update(['outcome' => $validated['new_outcome']]);

            return response()->json(['status' => 'overridden', 'override_id' => $result->id]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['code' => 'validation_failed', 'message' => $e->getMessage()], 422);
        }
    }
}
