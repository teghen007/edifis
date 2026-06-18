<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class IssueToken
{
    public function handle(array $credentials, ?string $deviceId = null): ?array
    {
        $user = User::where('email', $credentials['identifier'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        if (! $user->active) {
            return null;
        }

        $token = $user->createToken(
            name: $deviceId ?? 'edifis-token',
            expiresAt: now()->addMinutes(config('edifis.sanctum_token_ttl_minutes', 120))
        );

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601ZuluString(),
            'role' => $user->getRoleNames()->first() ?? 'parent',
            'user_id' => $user->id,
        ];
    }
}
