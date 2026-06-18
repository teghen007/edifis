<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Ledger\Queries\BalanceQuery;
use Illuminate\Http\JsonResponse;

class FeesController
{
    public function balance(string $studentId, BalanceQuery $query): JsonResponse
    {
        return response()->json($query->get($studentId));
    }
}
