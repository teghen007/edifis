<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Students\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPhotoController
{
    /** Photo Day: capture/replace a single student's photo from the app. */
    public function store(Request $request, string $studentId): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ]);

        $student = Student::findOrFail($studentId);

        $student->clearMediaCollection('photo');
        $student->addMediaFromRequest('photo')->toMediaCollection('photo');

        return response()->json([
            'id' => $student->id,
            'photo_url' => $student->getFirstMediaUrl('photo') ?: null,
        ]);
    }
}
