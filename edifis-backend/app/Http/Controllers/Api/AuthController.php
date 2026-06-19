<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Actions\IssueToken;
use App\Domain\Auth\Services\RevocationList;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function login(Request $request, IssueToken $issueToken): JsonResponse
    {
        try {
            $validated = $request->validate([
                'identifier' => ['required', 'string'],
                'password' => ['required', 'string'],
                'device_id' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $details[] = ['field' => $field, 'issue' => $msg];
                }
            }
            return response()->json([
                'code' => 'validation_failed',
                'message' => 'The given data was invalid.',
                'details' => $details,
                'retry_after_seconds' => null,
            ], 422);
        }

        $result = $issueToken->handle($validated, $validated['device_id'] ?? null);

        if ($result === null) {
            $user = \App\Models\User::where('email', $validated['identifier'])->first();
            $code = ($user && ! $user->active) ? 'account_deactivated' : 'invalid_credentials';

            return response()->json([
                'code' => $code,
                'message' => $code === 'account_deactivated'
                    ? 'This account has been deactivated.'
                    : 'The provided credentials are incorrect.',
                'details' => null,
                'retry_after_seconds' => null,
            ], 401);
        }

        return response()->json($result);
    }

    public function me(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'user_id'     => $u->id,
            'name'        => $u->name,
            'role'        => $u->getRoleNames()->first(),
            'email'       => $u->email,
            'school_name' => config('app.name'),
        ]);
    }

    public function revocations(Request $request, RevocationList $list): JsonResponse
    {
        $since = $request->query('since')
            ? Carbon::parse($request->query('since'))
            : null;

        return response()->json($list->revokedSince($since));
    }
}
