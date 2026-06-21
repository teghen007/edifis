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

    public function assignments(Request $request): JsonResponse
    {
        $u = $request->user();

        // Principals/VPs/admins are not stream-scoped — they see everything.
        $unscoped = $u->hasAnyRoleName(['principal', 'vice_principal', 'school_admin']);

        if ($unscoped) {
            $streams = \Illuminate\Support\Facades\DB::table('streams')
                ->select('id', 'name')->orderBy('name')->get();
            $subjects = \Illuminate\Support\Facades\DB::table('subjects')
                ->select('id', 'name', 'code')->orderBy('name')->get();
            $pairs = [];
            foreach ($streams as $s) {
                foreach ($subjects as $sub) {
                    $pairs[] = ['stream_id' => $s->id, 'subject_id' => $sub->id];
                }
            }

            return response()->json([
                'scoped' => false,
                'streams' => $streams,
                'subjects' => $subjects,
                'pairs' => $pairs,
            ]);
        }

        $rows = \Illuminate\Support\Facades\DB::table('teacher_assignments as ta')
            ->join('streams', 'ta.stream_id', '=', 'streams.id')
            ->join('subjects', 'ta.subject_id', '=', 'subjects.id')
            ->where('ta.teacher_id', $u->id)
            ->select('streams.id as stream_id', 'streams.name as stream_name',
                'subjects.id as subject_id', 'subjects.name as subject_name', 'subjects.code as subject_code')
            ->get();

        $streams = $rows->unique('stream_id')->map(fn ($r) => [
            'id' => $r->stream_id, 'name' => $r->stream_name,
        ])->values();
        $subjects = $rows->unique('subject_id')->map(fn ($r) => [
            'id' => $r->subject_id, 'name' => $r->subject_name, 'code' => $r->subject_code,
        ])->values();
        $pairs = $rows->map(fn ($r) => [
            'stream_id' => $r->stream_id, 'subject_id' => $r->subject_id,
        ])->values();

        return response()->json([
            'scoped' => true,
            'streams' => $streams,
            'subjects' => $subjects,
            'pairs' => $pairs,
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
