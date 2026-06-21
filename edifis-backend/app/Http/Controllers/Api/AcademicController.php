<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Term;
use Illuminate\Http\JsonResponse;

class AcademicController
{
    public function streams(): JsonResponse
    {
        $streams = Stream::with(['schoolClass', 'section', 'academicYear'])
            ->where('active', true)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'class_name' => $s->schoolClass?->name ?? '',
                'section_name' => $s->section?->name ?? '',
                'year' => $s->academicYear?->name ?? '',
            ]);

        return response()->json($streams);
    }

    public function terms(): JsonResponse
    {
        $terms = Term::with('tests')
            ->orderBy('position')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'position' => $t->position,
                'tests' => $t->tests->sortBy('position')->map(fn ($test) => [
                    'id' => $test->id,
                    'name' => $test->name,
                ])->values(),
            ]);

        return response()->json($terms);
    }
}
