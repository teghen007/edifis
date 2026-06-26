<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Academics\Services\SeasonService;
use Illuminate\Http\JsonResponse;

class SeasonController
{
    public function __construct(private readonly SeasonService $season)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->season->current());
    }

    public function years(): JsonResponse
    {
        return response()->json($this->season->years());
    }

    public function openNextSequence(): JsonResponse
    {
        return response()->json($this->season->openNextSequence());
    }

    public function advance(): JsonResponse
    {
        return response()->json($this->season->advanceTerm());
    }

    public function reopen(string $termId): JsonResponse
    {
        return response()->json($this->season->reopenTerm($termId));
    }

    public function closeYear(): JsonResponse
    {
        return response()->json($this->season->closeYear());
    }
}
