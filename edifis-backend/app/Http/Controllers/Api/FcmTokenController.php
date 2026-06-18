<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Notifications\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class FcmTokenController
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $userId = $request->user()->id;

        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'id' => (string) Uuid::uuid7(),
                'user_id' => $userId,
                'device_name' => $validated['device_name'] ?? null,
            ]
        );

        return response()->json(['status' => 'registered']);
    }
}
