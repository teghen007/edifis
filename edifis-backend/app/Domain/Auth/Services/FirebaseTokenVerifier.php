<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifies a Firebase ID token (RS256) issued by Firebase Phone Auth, without any
 * external SDK: validates signature against Google's public x509 certs + the
 * standard iss/aud/exp claims. Returns the decoded payload or null if invalid.
 */
class FirebaseTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function verify(string $idToken, string $projectId): ?array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3 || $projectId === '') {
            return null;
        }
        [$h, $p, $s] = $parts;

        $header = json_decode($this->b64url($h), true);
        $payload = json_decode($this->b64url($p), true);
        $signature = $this->b64url($s);

        if (!is_array($header) || !is_array($payload)) {
            return null;
        }

        // Standard Firebase ID-token claims.
        if (($payload['aud'] ?? null) !== $projectId) {
            return null;
        }
        if (($payload['iss'] ?? null) !== "https://securetoken.google.com/{$projectId}") {
            return null;
        }
        if ((int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            return null;
        }

        $cert = $this->publicCert($header['kid']);
        if (!$cert) {
            return null;
        }

        $pubKey = openssl_pkey_get_public($cert);
        if ($pubKey === false) {
            return null;
        }

        $ok = openssl_verify("{$h}.{$p}", $signature, $pubKey, OPENSSL_ALGO_SHA256);

        return $ok === 1 ? $payload : null;
    }

    private function publicCert(string $kid): ?string
    {
        $certs = Cache::remember('firebase_securetoken_certs', 3600, function () {
            $res = Http::timeout(10)->get(self::CERTS_URL);
            return $res->successful() ? $res->json() : [];
        });

        return $certs[$kid] ?? null;
    }

    private function b64url(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
