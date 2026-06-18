<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Onboarding\Models\SchoolRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class PublicWebsiteController
{
    public function landing()
    {
        return view('public.landing');
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'school_code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9\-]+$/'],
            'location' => ['nullable', 'string'],
            'contact_name' => ['required', 'string'],
            'contact_email' => ['required', 'email'],
            'contact_phone' => ['nullable', 'string'],
            'estimated_students' => ['nullable', 'integer', 'min:1'],
        ]);

        // Idempotent: skip if a request already exists for this school code
        $exists = SchoolRequest::where('school_code', $validated['school_code'])
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'already_submitted',
                'message' => 'A request for this school code is already pending review.',
            ], 200);
        }

        SchoolRequest::create([
            'id' => (string) Uuid::uuid7(),
            'school_name' => $validated['school_name'],
            'school_code' => $validated['school_code'],
            'location' => $validated['location'] ?? null,
            'contact_name' => $validated['contact_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'] ?? null,
            'estimated_students' => $validated['estimated_students'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'submitted',
            'message' => 'Your request has been submitted. PEA will review it and notify you by email.',
        ], 201);
    }
}
