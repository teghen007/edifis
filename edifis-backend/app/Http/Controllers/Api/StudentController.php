<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Students\Actions\EnrolStudent;
use App\Domain\Students\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController
{
    public function index(Request $request): JsonResponse
    {
        $students = Student::where('active', true)
            ->orderBy('family_name')
            ->orderBy('given_name')
            ->get()
            ->map(fn (Student $s) => [
                'id'         => $s->id,
                'name'       => trim($s->given_name . ' ' . $s->family_name),
                'class_name' => '',
                'active'     => (bool) $s->active,
            ]);

        return response()->json($students);
    }

    public function store(Request $request, EnrolStudent $enrolStudent): JsonResponse
    {
        $validated = $request->validate([
            'student' => ['required', 'array'],
            'student.given_name' => ['required', 'string'],
            'student.family_name' => ['required', 'string'],
            'student.other_names' => ['nullable', 'string'],
            'student.sex' => ['nullable', 'in:M,F'],
            'student.date_of_birth' => ['nullable', 'date'],
            'student.current_class_id' => ['required', 'uuid'],
            'student.photo_ref' => ['nullable', 'string'],
            'consent' => ['required', 'array'],
            'consent.consenter_name' => ['required', 'string'],
            'consent.relationship' => ['required', 'in:mother,father,guardian,other'],
            'consent.consenter_contact' => ['nullable', 'string'],
            'consent.scope' => ['required', 'array', 'min:1'],
        ]);

        try {
            $student = $enrolStudent->handle($validated);
            return response()->json($student, 201);
        } catch (\App\Exceptions\ConsentRequiredException $e) {
            return response()->json([
                'code' => 'consent_required',
                'message' => 'Valid parental/guardian consent is required to enrol a minor.',
                'details' => null,
                'retry_after_seconds' => null,
            ], 422);
        }
    }
}
