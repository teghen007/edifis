<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'mode' => config('edifis.mode'),
            'version' => config('app.version', '0.1.0'),
        ]);
    }
}
