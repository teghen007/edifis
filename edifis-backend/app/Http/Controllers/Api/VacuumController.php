<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Vacuum\Actions\RunQuery;
use App\Domain\Vacuum\Actions\RunCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacuumController
{
    public function query(Request $request, RunQuery $runQuery): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string'],
        ]);

        try {
            $result = $runQuery->handle($request->user(), $validated['question']);
            return response()->json($result);
        } catch (\App\Exceptions\NodeModeUnsupportedException $e) {
            return response()->json([
                'code' => 'forbidden',
                'message' => $e->getMessage(),
                'details' => null,
                'retry_after_seconds' => null,
            ], 403);
        }
    }

    public function command(Request $request, RunCommand $runCommand): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'in:correct_mark,override_promotion,deactivate_account'],
            'target' => ['required', 'array'],
            'payload' => ['nullable', 'array'],
            'reason' => ['required', 'string'],
            'confirm' => ['boolean'],
        ]);

        try {
            $result = $runCommand->handle(
                principal: $request->user(),
                command: $validated['command'],
                target: $validated['target'],
                payload: $validated['payload'] ?? [],
                reason: $validated['reason'],
                confirm: $validated['confirm'] ?? false,
            );

            return response()->json($result);
        } catch (\App\Exceptions\NodeModeUnsupportedException $e) {
            return response()->json([
                'code' => 'forbidden',
                'message' => $e->getMessage(),
                'details' => null,
                'retry_after_seconds' => null,
            ], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'code' => 'validation_failed',
                'message' => $e->getMessage(),
                'details' => null,
                'retry_after_seconds' => null,
            ], 422);
        }
    }
}
