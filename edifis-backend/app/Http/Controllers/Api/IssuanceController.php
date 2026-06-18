<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Issuance\Actions\IssueItemsToStudent;
use App\Domain\Issuance\Actions\ReturnItem;
use App\Domain\Issuance\Actions\ImportCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssuanceController
{
    public function import(Request $request, ImportCatalogue $import): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.name' => ['required', 'string'],
            'rows.*.cost' => ['required', 'integer', 'min:0'],
            'rows.*.category' => ['nullable', 'string'],
        ]);

        $imported = $import->handle($validated['rows']);

        return response()->json(['imported' => count($imported)], 202);
    }

    public function issue(Request $request, IssueItemsToStudent $action): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'uuid'],
            'student_id' => ['required', 'uuid'],
            'signature_ref' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalogue_item_id' => ['required', 'uuid'],
        ]);

        $result = $action->handle(
            batchId: $validated['batch_id'],
            studentId: $validated['student_id'],
            items: $validated['items'],
            signatureRef: $validated['signature_ref'],
            staffId: $request->user()->id,
        );

        if (($result['status'] ?? null) === 'replay') {
            return response()->json([
                'code' => 'idempotency_replay',
                'message' => 'This batch was already applied.',
                'details' => null,
                'retry_after_seconds' => null,
            ], 200);
        }

        return response()->json([
            'events' => $result['events'],
            'posted' => $result['posted'],
        ], 201);
    }

    public function return(Request $request, ReturnItem $action): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'uuid'],
            'reason' => ['required', 'string'],
        ]);

        $result = $action->handle(
            issueEventId: $validated['event_id'],
            reason: $validated['reason'],
            staffId: $request->user()->id,
        );

        return response()->json($result, 201);
    }
}
