<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenancyController
{
    /**
     * Public, fast, no side effects. Returns 200 if the domain is a registered
     * tenant domain, 404 otherwise. Used by Caddy's on-demand TLS to decide
     * whether to request a certificate for this hostname.
     */
    public function domainAllowed(Request $request): JsonResponse
    {
        $host = $request->query('domain');

        if (empty($host)) {
            return response()->json(null, 404);
        }

        $exists = DB::table('domains')
            ->where('domain', $host)
            ->exists();

        if ($exists) {
            return response()->json(['allowed' => true, 'domain' => $host]);
        }

        return response()->json(null, 404);
    }
}
