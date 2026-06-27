<?php

use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Renders every staff-panel page + resource list as a user holding all staff
 * roles, and fails if any returns a 5xx. This catches the class of bug where a
 * Filament page 500s on render (e.g. a form bound to a missing property, a
 * resource pointing at a non-existent model, a blank table that throws).
 */
it('every staff-panel page renders without a server error', function () {
    $roles = [
        'school_admin', 'principal', 'vice_principal', 'bursar',
        'class_master', 'subject_teacher', 'discipline_master', 'secretary',
    ];

    $admin = User::create([
        'id' => tid('user.smoke.admin'),
        'name' => 'Smoke Admin',
        'email' => 'smoke.admin@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    foreach ($roles as $role) {
        Role::findOrCreate($role);
        $admin->assignRole($role);
    }

    $panel = Filament::getPanel('staff');
    Filament::setCurrentPanel($panel);

    // Collect every page URL: custom Pages + each Resource's "index" (list) page.
    $targets = [];

    foreach ($panel->getPages() as $page) {
        try {
            $targets[$page] = $page::getUrl();
        } catch (\Throwable $e) {
            // URL generation needs no record — skip anything that can't build one.
        }
    }

    foreach ($panel->getResources() as $resource) {
        if (array_key_exists('index', $resource::getPages())) {
            $targets[$resource] = $resource::getUrl('index');
        }
    }

    expect($targets)->not->toBeEmpty('No panel pages were discovered.');

    $failures = [];
    foreach ($targets as $label => $url) {
        $status = actingAs($admin)->get($url)->getStatusCode();
        if ($status >= 500) {
            $failures[] = "{$label} ({$url}) → HTTP {$status}";
        }
    }

    expect($failures)->toBe([], 'These panel pages returned a server error:' . PHP_EOL . implode(PHP_EOL, $failures));
});
