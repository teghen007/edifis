<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\School\Models\SchoolSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SchoolHomeController
{
    public function index(): View
    {
        $settings = SchoolSetting::current();
        $tenant = tenant();

        $logo = $settings->logo_url;
        if ($logo && ! Str::startsWith($logo, ['http://', 'https://'])) {
            $logo = Storage::disk('public')->url($logo);
        }

        return view('school-home', [
            'schoolName' => $settings->name ?: ($tenant?->school_name ?? config('app.name')),
            'motto' => $settings->motto,
            'logo' => $logo,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'address' => $settings->address,
            'principalName' => $settings->principal_name,
            'parentUrl' => null,
        ]);
    }
}
