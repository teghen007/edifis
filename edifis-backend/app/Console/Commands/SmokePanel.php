<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Filament\Resources\StaffUserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Renders every staff-panel page + resource list as an all-roles admin and
 * reports any that throw (the same protection the CI smoke test gives, but
 * runnable on the live server right now). Uses a throwaway user it cleans up.
 */
class SmokePanel extends Command
{
    protected $signature = 'edifis:smoke-panel';

    protected $description = 'Render every staff-panel page and report any that 500';

    public function handle(): int
    {
        $this->newLine();
        $this->line('Running as: <fg=yellow>' . $this->effectiveUser() . '</>');
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->warn('Running as root — root can write any cache file, so storage-permission '
                . 'problems that break the www-data web user may pass here. After a deploy run: '
                . 'docker compose exec -u www-data app php artisan edifis:smoke-panel');
        }

        // Canary for the "root-owned cache poisons www-data" outage: any compiled
        // cache file the current user can't overwrite would 500 a real web request.
        $storageFailures = $this->checkStorageWritable();

        // Don't pollute the activity log with the throwaway user's create/delete.
        app(\Spatie\Activitylog\ActivityLogStatus::class)->disable();

        $user = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Panel Smoke',
            'email' => 'panel-smoke-' . uniqid() . '@local',
            'password' => bcrypt(\Illuminate\Support\Str::random(20)),
            'active' => true,
        ]);

        foreach (StaffUserResource::STAFF_ROLES as $roleName) {
            if (Role::where('name', $roleName)->exists()) {
                $user->assignRole($roleName);
            }
        }

        Auth::guard('web')->login($user);
        $panel = Filament::getPanel('staff');
        Filament::setCurrentPanel($panel);

        // Collect targets: custom Pages + each Resource's "index" (list) page.
        $targets = [];
        foreach ($panel->getPages() as $page) {
            $targets[class_basename($page)] = $page;
        }
        foreach ($panel->getResources() as $resource) {
            $pages = $resource::getPages();
            if (isset($pages['index'])) {
                $targets[class_basename($resource) . ' (list)'] = $pages['index']->getPage();
            }
            // Create pages render the form (catches broken form fields, e.g. media uploads).
            if (isset($pages['create'])) {
                $targets[class_basename($resource) . ' (create)'] = $pages['create']->getPage();
            }
        }

        $failures = 0;
        $this->newLine();
        try {
            foreach ($targets as $label => $class) {
                try {
                    Livewire::test($class);
                    $this->line("  <fg=green>OK  </> {$label}");
                } catch (\Throwable $e) {
                    $failures++;
                    $this->line("  <fg=red>FAIL</> {$label}");
                    $this->line('       ' . class_basename($e) . ': ' . $e->getMessage());
                }
            }
        } finally {
            $user->forceDelete();
        }

        $this->newLine();
        $total = count($targets);
        if ($failures === 0 && $storageFailures === 0) {
            $this->info("All {$total} panel pages rendered cleanly; storage cache writable.");

            return self::SUCCESS;
        }

        if ($failures > 0) {
            $this->error("{$failures} of {$total} panel pages FAILED to render.");
        }
        if ($storageFailures > 0) {
            $this->error("{$storageFailures} storage cache location(s) not writable by the current user.");
        }

        return self::FAILURE;
    }

    private function effectiveUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());

            return $info['name'] ?? ('uid:' . posix_geteuid());
        }

        return get_current_user() ?: (getenv('USER') ?: 'unknown');
    }

    /**
     * Flags the exact condition behind the login-500 outage: a compiled cache
     * file (or its directory) the current OS user cannot overwrite. Run as
     * www-data, this catches root-owned cache left by deploy commands.
     */
    private function checkStorageWritable(): int
    {
        $failures = 0;
        $dirs = [
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            if (! is_writable($dir)) {
                $failures++;
                $this->line("  <fg=red>FAIL</> directory not writable: {$dir}");

                continue;
            }
            $unwritable = [];
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file) && ! is_writable($file)) {
                    $unwritable[] = basename($file);
                }
            }
            if ($unwritable !== []) {
                $failures++;
                $this->line('  <fg=red>FAIL</> ' . count($unwritable) . " file(s) in {$dir} not writable by current user "
                    . "(e.g. {$unwritable[0]}) — run cache/artisan commands as www-data");
            } else {
                $this->line("  <fg=green>OK  </> writable: {$dir}");
            }
        }

        return $failures;
    }
}
