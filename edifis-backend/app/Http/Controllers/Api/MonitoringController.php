<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Monitoring\Actions\PostNodeStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController
{
    public function nodeStatus(Request $request, PostNodeStatus $postNode): JsonResponse
    {
        $validated = $request->validate([
            'node_id' => ['required', 'string'],
            'reported_at' => ['nullable', 'date'],
            'disk_ok' => ['nullable', 'boolean'],
            'ups_on_battery' => ['nullable', 'boolean'],
            'last_sync_at' => ['nullable', 'date'],
            'pending_outbox' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $postNode->handle($validated);

        return response()->json($result, 202);
    }
}
