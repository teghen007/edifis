<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\School\Models\SchoolSetting;
use Illuminate\Http\JsonResponse;

class SchoolController
{
    public function profile(): JsonResponse
    {
        $s = SchoolSetting::current();

        return response()->json([
            'name' => $s->name ?? '',
            'school_type' => $s->school_type ?? 'day',
            'motto' => $s->motto ?? '',
            'logo_url' => $s->logo_url ?? '',
            'currency' => $s->currency ?? 'XAF',
            'principal_name' => $s->principal_name ?? '',
            'address' => $s->address ?? '',
            'phone' => $s->phone ?? '',
            'email' => $s->email ?? '',
        ]);
    }
}
