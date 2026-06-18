<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Ramsey\Uuid\Uuid;

class SetParentPin
{
    public function handle(User $user, string $pin): User
    {
        if (strlen($pin) < 4 || strlen($pin) > 6 || ! ctype_digit($pin)) {
            throw new \InvalidArgumentException('PIN must be 4-6 digits.');
        }

        $key = 'set-pin:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new \RuntimeException('Too many PIN set attempts. Try again later.');
        }

        RateLimiter::hit($key, 60);

        $user->update([
            'pin_hash' => Hash::make($pin),
            'must_reset_credential' => false,
        ]);

        return $user->fresh();
    }
}
