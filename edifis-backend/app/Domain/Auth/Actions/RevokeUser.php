<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeUser
{
    public function handle(User $user, string $reason = 'disabled'): void
    {
        if (! $user->active) {
            return; // idempotent — already revoked
        }

        DB::transaction(function () use ($user) {
            $user->update(['active' => false]);

            DB::table('revocations')->insertOrIgnore([
                'user_id' => $user->id,
                'token' => null,
                'revoked_at' => now(),
            ]);

            $user->tokens()->delete();
        });
    }
}
