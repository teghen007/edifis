<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Tenancy\Models\EdifisTenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class OnboardSchool extends Command
{
    protected $signature = 'edifis:onboard-school
                            {code : School code, e.g. pssnkwen}
                            {--name= : School display name}
                            {--principal-email= : Email for the first Principal account}';

    protected $description = 'Register a new school tenant with domain, starter seed, and Principal. Idempotent.';

    public function handle(): int
    {
        $code = $this->argument('code');
        $name = $this->option('name') ?: strtoupper($code);
        $principalEmail = $this->option('principal-email') ?: "principal@{$code}.myedifis.com";

        $domain = "{$code}.myedifis.com";

        // --- Tenant (idempotent) ---
        $tenant = EdifisTenant::firstOrCreate(
            ['id' => $code],
            [
                'school_code' => $code,
                'school_name' => $name,
                'school_location' => 'Cameroon',
            ]
        );

        $wasNewTenant = $tenant->wasRecentlyCreated;

        // --- Domain (idempotent) ---
        Domain::firstOrCreate(
            ['domain' => $domain],
            ['tenant_id' => $tenant->id],
        );

        // --- Run tenant migrations ---
        if ($wasNewTenant) {
            $tenant->run(function () {
                $this->call('migrate', ['--force' => true]);
            });
            $this->info("[tenant] Migrations complete for {$code}.");
        }

        // --- Roles & permissions ---
        tenancy()->initialize($tenant);
        $this->call('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);
        tenancy()->end();

        // --- Starter seed (catalogue) ---
        tenancy()->initialize($tenant);
        $this->call('db:seed', ['--class' => 'LabSeeder', '--force' => true]);
        tenancy()->end();

        // --- Principal with one-time claim code ---
        $claimCode = Str::upper(Str::random(8));

        tenancy()->initialize($tenant);

        $principal = User::firstOrCreate(
            ['email' => $principalEmail],
            [
                'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
                'name' => "Principal — {$name}",
                'password' => Hash::make($claimCode),
                'must_reset_credential' => true,
                'active' => true,
            ]
        );

        if ($principal->wasRecentlyCreated) {
            $principal->assignRole('principal');
        }

        tenancy()->end();

        // --- Report ---
        $this->info('');
        $this->info('=== School Onboarded ===');
        $this->info("Code:    {$code}");
        $this->info("Domain:  https://{$domain}");
        $this->info("Login:   https://{$domain}/staff");
        $this->info("Email:   {$principalEmail}");
        $this->info("Claim:   {$claimCode}  (one-time — forces reset on first login)");
        $this->info('');
        $this->info(($wasNewTenant ? 'CREATED' : 'Already existed (idempotent)') . ' — all seeds reapplied.');

        return self::SUCCESS;
    }
}
