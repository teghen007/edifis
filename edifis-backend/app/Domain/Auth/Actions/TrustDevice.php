<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Models\TrustedDevice;
use App\Models\User;

class TrustDevice
{
    /**
     * Register a new trusted device. Returns the cookie secret (bin2hex(random_bytes(32))).
     * The DB stores only hash('sha256', secret) — a DB leak can't forge cookies.
     */
    public function handle(User $user, string $deviceName): string
    {
        $secret = bin2hex(random_bytes(32));

        TrustedDevice::create([
            'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
            'user_id' => $user->id,
            'device_token' => hash('sha256', $secret),
            'device_name' => $deviceName ?: request()->userAgent() ?? 'Unknown Device',
            'trusted_until' => now()->addDays(90),
        ]);

        return $secret;
    }

    /**
     * Check if a cookie secret matches a trusted device for this user.
     * User-bound + hash-compared — a stored row alone is useless as a cookie.
     */
    public function isTrusted(string $userId, string $cookieSecret): bool
    {
        return TrustedDevice::where('user_id', $userId)
            ->where('device_token', hash('sha256', $cookieSecret))
            ->where('trusted_until', '>', now())
            ->exists();
    }
}
