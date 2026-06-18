<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RevocationList
{
    public function revokedSince(?CarbonInterface $since): array
    {
        $query = DB::table('revocations');

        if ($since) {
            $query->where('revoked_at', '>', $since);
        }

        $revocations = $query->get();

        return [
            'revoked_token_ids' => $revocations->pluck('token')->unique()->values()->toArray(),
            'revoked_user_ids' => $revocations->pluck('user_id')->unique()->values()->toArray(),
            'as_of' => now()->toIso8601ZuluString(),
        ];
    }

    public function isRevoked(string $token): bool
    {
        return DB::table('revocations')->where('token', $token)->exists();
    }
}
