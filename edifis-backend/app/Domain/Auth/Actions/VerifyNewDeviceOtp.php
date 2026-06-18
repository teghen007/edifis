<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Ramsey\Uuid\Uuid;

class VerifyNewDeviceOtp
{
    public function send(User $user, string $deviceName): LoginOtp
    {
        $code = (string) random_int(100000, 999999);

        LoginOtp::where('user_id', $user->id)->delete();

        $otp = LoginOtp::create([
            'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
            'user_id' => $user->id,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        if ($user->email) {
            \Illuminate\Support\Facades\Mail::to($user->email)->queue(new \App\Mail\OtpMail($code));
        }

        if (app()->environment(['local', 'testing'])) {
            \Illuminate\Support\Facades\Log::info("OTP for {$user->email}: {$code}");
        }

        return $otp;
    }

    public function verify(User $user, string $code): bool
    {
        $key = 'otp-verify:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new \RuntimeException('Too many OTP attempts. Try again later.');
        }

        RateLimiter::hit($key, 300);

        $otp = LoginOtp::where('user_id', $user->id)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->increment('attempts');

        if ($otp->attempts > 5) {
            $otp->update(['used' => true]);
            return false;
        }

        if (! Hash::check($code, $otp->otp_hash)) {
            return false;
        }

        $otp->update(['used' => true]);
        return true;
    }
}
