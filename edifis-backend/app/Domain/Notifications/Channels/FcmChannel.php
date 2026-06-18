<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\FcmToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmChannel
{
    private ?string $cachedAccessToken = null;
    private ?int $tokenExpiresAt = null;

    /**
     * Send via FCM HTTP v1. The legacy endpoint (fcm/send with server key)
     * was permanently retired by Google in June 2024.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        $tokens = FcmToken::where('user_id', $notifiable->id)->pluck('token');
        $projectId = config('services.fcm.project_id');

        if (! $projectId || $tokens->isEmpty()) {
            return;
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            Log::warning('FCM v1: could not obtain access token');
            return;
        }

        foreach ($tokens as $token) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $payload['title'] ?? 'EDIFIS',
                            'body' => $payload['body'] ?? '',
                        ],
                        'data' => collect($payload['data'] ?? [])
                            ->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])
                            ->toArray(),
                    ],
                ]
            );

            if (! $response->successful()) {
                Log::warning('FCM v1 send failed', [
                    'token_prefix' => substr($token, 0, 10) . '...',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($response->status() === 404 && str_contains($response->body(), 'UNREGISTERED')) {
                    FcmToken::where('token', $token)->delete();
                }
            }
        }
    }

    /** Obtain an OAuth2 Bearer token from the service-account JSON key. */
    private function getAccessToken(): ?string
    {
        if ($this->cachedAccessToken && $this->tokenExpiresAt && $this->tokenExpiresAt > time()) {
            return $this->cachedAccessToken;
        }

        // Test/simulation mode: skip OAuth if a static token is provided
        $testToken = config('services.fcm.test_access_token');
        if ($testToken) {
            $this->cachedAccessToken = $testToken;
            $this->tokenExpiresAt = time() + 3600;
            return $testToken;
        }

        $credentialsPath = config('services.fcm.credentials_path');

        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            Log::warning('FCM v1: service account credentials not found at ' . ($credentialsPath ?? 'null'));
            return null;
        }

        $sa = json_decode(file_get_contents($credentialsPath), true);
        $privateKey = $sa['private_key'] ?? null;
        $clientEmail = $sa['client_email'] ?? null;

        if (! $privateKey || ! $clientEmail) {
            return null;
        }

        $jwt = $this->buildJwt($clientEmail, $privateKey);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->cachedAccessToken = $data['access_token'] ?? null;
            $this->tokenExpiresAt = time() + ($data['expires_in'] ?? 3600) - 60;
            return $this->cachedAccessToken;
        }

        return null;
    }

    private function buildJwt(string $email, string $privateKey): string
    {
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();

        $payload = base64_encode(json_encode([
            'iss' => $email,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = $header . '.' . $payload;
        openssl_sign($signingInput, $signature, $privateKey, 'SHA256');
        $jwtSig = $this->base64UrlEncode($signature);

        return $signingInput . '.' . $jwtSig;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
