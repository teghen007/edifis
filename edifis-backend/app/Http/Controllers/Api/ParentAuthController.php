<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Actions\SetParentPin;
use App\Domain\Auth\Actions\TrustDevice;
use App\Domain\Auth\Actions\VerifyNewDeviceOtp;
use App\Domain\Auth\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ParentAuthController
{
    public function login(Request $request, VerifyNewDeviceOtp $otp, TrustDevice $trust): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'credential' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
            'device_token' => ['nullable', 'string'],
        ]);

        $rateKey = 'parent-login:' . $validated['phone'];

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return response()->json([
                'code' => 'rate_limited',
                'message' => 'Too many login attempts. Try again later.',
                'details' => null,
                'retry_after_seconds' => RateLimiter::availableIn($rateKey),
            ], 429);
        }

        RateLimiter::hit($rateKey, 60);

        $user = User::where('phone', $validated['phone'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'parent'))
            ->first();

        if (! $user) {
            return response()->json(['code' => 'invalid_credentials', 'message' => 'Invalid credentials.'], 401);
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return response()->json(['code' => 'account_locked', 'message' => 'Account is locked.'], 423);
        }

        // Check bootstrap credential (phone reversed) or PIN
        $isBootstrap = $user->must_reset_credential;
        $credentialValid = false;

        if ($isBootstrap) {
            $phoneReversed = strrev($validated['phone']);
            $credentialValid = $validated['credential'] === $phoneReversed;
        } else {
            $credentialValid = Hash::check($validated['credential'], $user->pin_hash);
        }

        if (! $credentialValid) {
            $user->increment('login_attempts');
            if ($user->login_attempts >= 10) {
                $user->update(['locked_until' => now()->addHours(1)]);
            }
            return response()->json(['code' => 'invalid_credentials', 'message' => 'Invalid credentials.'], 401);
        }

        $user->update(['login_attempts' => 0, 'locked_until' => null]);

        // Check trusted device
        $deviceToken = $validated['device_token'] ?? null;
        $isTrustedDevice = $deviceToken && $trust->isTrusted($user->id, $deviceToken);

        // New-device OTP (skip if trusted)
        if (! $isTrustedDevice && $user->email) {
            $otp->send($user, $validated['device_name'] ?? 'Parent Portal');

            return response()->json([
                'status' => 'otp_required',
                'message' => 'A 6-digit code has been sent to your email.',
                'must_reset_pin' => $isBootstrap,
            ]);
        }

        // Trust this device for future logins
        $deviceSecret = null;
        if ($deviceToken) {
            $deviceSecret = $deviceToken; // already trusted, reuse
        } else {
            $deviceSecret = $trust->handle($user, $validated['device_name'] ?? 'Parent Portal');
        }

        $token = $user->createToken('parent-portal', ['parent'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'must_reset_pin' => $isBootstrap,
            'device_trusted' => $isTrustedDevice,
            'device_token' => $deviceSecret,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Login via Firebase Phone Auth: the app verifies the phone by SMS with Firebase,
     * then sends us the Firebase ID token. We verify it and match the phone to a parent.
     */
    public function firebaseLogin(Request $request, \App\Domain\Auth\Services\FirebaseTokenVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $projectId = (string) config('services.fcm.project_id');
        $payload = $verifier->verify($validated['id_token'], $projectId);

        if (!$payload || empty($payload['phone_number'])) {
            return response()->json(['code' => 'invalid_token', 'message' => 'Phone verification failed. Please try again.'], 401);
        }

        $user = User::where('phone', $payload['phone_number'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'parent'))
            ->first();

        if (!$user) {
            return response()->json([
                'code' => 'no_account',
                'message' => 'No parent account is registered for this phone number. Please contact the school office.',
            ], 404);
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return response()->json(['code' => 'account_locked', 'message' => 'Account is locked.'], 423);
        }

        $token = $user->createToken('parent-portal', ['parent'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'must_reset_pin' => (bool) $user->must_reset_credential,
            'user_id' => $user->id,
        ]);
    }

    public function setPin(Request $request, SetParentPin $setPin): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:6'],
        ]);

        $user = $request->user();

        if (! $user->hasRole('parent')) {
            return response()->json(['code' => 'forbidden', 'message' => 'Not a parent account.'], 403);
        }

        $setPin->handle($user, $validated['pin']);

        return response()->json(['status' => 'pin_set']);
    }

    public function verifyOtp(Request $request, VerifyNewDeviceOtp $otp, TrustDevice $trust): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = User::where('phone', $validated['phone'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'parent'))
            ->firstOrFail();

        if (! $otp->verify($user, $validated['code'])) {
            return response()->json(['code' => 'invalid_otp', 'message' => 'Invalid or expired code.'], 422);
        }

        $deviceSecret = $trust->handle($user, $validated['device_name'] ?? 'Parent Portal');
        $token = $user->createToken('parent-portal', ['parent'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'must_reset_pin' => $user->must_reset_credential,
            'device_token' => $deviceSecret,
        ]);
    }
}
