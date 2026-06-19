<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Academics\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController
{
    public function index(Request $request): JsonResponse
    {
        $subjects = Subject::where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ]);

        return response()->json($subjects);
    }
}
