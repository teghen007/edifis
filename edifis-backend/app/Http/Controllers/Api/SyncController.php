<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Sync\Actions\ApplyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SyncController
{
    public function __invoke(Request $request, ApplyEnvelope $apply): JsonResponse
    {
        $key = 'sync:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, config('sync.rate_limit_per_minute', 120))) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'code' => 'rate_limited',
                'message' => 'Too many sync requests. Retry after backoff.',
                'details' => null,
                'retry_after_seconds' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'direction' => ['required', 'in:push,pull'],
            'node_id' => ['required', 'string'],
            'since_cursor' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
        ]);

        if ($validated['direction'] === 'push') {
            $result = $apply->push($validated);

            return response()->json([
                'direction' => 'push',
                'node_id' => $validated['node_id'],
                'since_cursor' => $validated['since_cursor'] ?? null,
                'items' => [],
                'conflicts' => $result['conflicts'],
            ]);
        }

        return response()->json(
            $apply->pull($validated['node_id'], $validated['since_cursor'] ?? null)
        );
    }
}
