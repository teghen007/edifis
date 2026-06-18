<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Onboarding\Actions\ApproveSchoolRequest;
use App\Domain\Onboarding\Models\SchoolRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController
{
    public function list(): JsonResponse
    {
        return response()->json(
            SchoolRequest::orderByDesc('created_at')->get()
        );
    }

    public function approve(string $id, Request $request, ApproveSchoolRequest $approve): JsonResponse
    {
        $school = SchoolRequest::findOrFail($id);

        $result = $approve->handle($school, $request->user()->id);

        return response()->json([
            'status' => 'approved',
            'school_code' => $result->school_code,
            'claim_code' => $result->claim_code,
            'login_url' => "https://{$result->school_code}.myedifis.com/staff",
        ]);
    }
}
