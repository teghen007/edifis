<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Academics\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolClassController
{
    public function index(Request $request): JsonResponse
    {
        $classes = SchoolClass::where('active', true)
            ->orderBy('level')
            ->get()
            ->map(fn (SchoolClass $c) => [
                'id'    => $c->id,
                'name'  => $c->name,
                'level' => $c->level,
            ]);

        return response()->json($classes);
    }
}
