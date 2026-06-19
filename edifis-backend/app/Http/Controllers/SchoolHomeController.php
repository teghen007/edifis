<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class SchoolHomeController
{
    public function index(): View
    {
        $tenant = tenant();
        $schoolName = $tenant?->school_name ?? config('app.name');

        return view('school-home', [
            'schoolName' => $schoolName,
            'parentUrl' => null,
        ]);
    }
}
