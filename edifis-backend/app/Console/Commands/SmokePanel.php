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
        if ($failures === 0) {
            $this->info("All {$total} panel pages rendered cleanly.");

            return self::SUCCESS;
        }

        $this->error("{$failures} of {$total} panel pages FAILED to render.");

        return self::FAILURE;
    }
}
