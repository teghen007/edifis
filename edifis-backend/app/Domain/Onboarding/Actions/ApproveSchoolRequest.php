<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Models\SchoolRequest;
use App\Domain\Audit\Services\AuditLogger;
use App\Mail\OnboardingApproved;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ApproveSchoolRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(SchoolRequest $request, string $peaAdminId): SchoolRequest
    {
        if ($request->status !== 'pending') {
            throw new \RuntimeException("Request {$request->id} is already {$request->status}.");
        }

        // Onboard the school: create tenant + domain + principal directly
        $tenant = \App\Domain\Tenancy\Models\EdifisTenant::firstOrCreate(
            ['id' => $request->school_code],
            [
                'school_code' => $request->school_code,
                'school_name' => $request->school_name,
                'school_location' => $request->location ?? 'Cameroon',
            ]
        );

        \Stancl\Tenancy\Database\Models\Domain::firstOrCreate(
            ['domain' => $request->school_code . '.myedifis.com'],
            ['tenant_id' => $tenant->id],
        );

        $claimCode = strtoupper(\Illuminate\Support\Str::random(8));

        tenancy()->initialize($tenant);
        try {
            \App\Models\User::firstOrCreate(
                ['email' => $request->contact_email],
                [
                    'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
                    'name' => "Principal — {$request->school_name}",
                    'password' => Hash::make($claimCode),
                    'must_reset_credential' => true,
                    'active' => true,
                ]
            );

            \Spatie\Permission\Models\Role::findOrCreate('principal');
            \Spatie\Permission\Models\Role::findOrCreate('parent');

            $principal = \App\Models\User::where('email', $request->contact_email)->first();
            if ($principal && ! $principal->hasRole('principal')) {
                $principal->assignRole('principal');
            }
        } finally {
            tenancy()->end();
        }

        $request->update([
            'status' => 'approved',
            'approved_by' => $peaAdminId,
            'approved_at' => now(),
            'claim_code' => $claimCode,
        ]);

        // Email the claim code to the principal
        if ($claimCode) {
            Mail::to($request->contact_email)->send(
                new OnboardingApproved(
                    schoolName: $request->school_name,
                    schoolCode: $request->school_code,
                    claimCode: $claimCode,
                    loginUrl: "https://{$request->school_code}.myedifis.com/staff",
                )
            );
        }

        $this->audit->log(
            actorId: $peaAdminId,
            actorRole: \App\Models\User::find($peaAdminId)?->getRoleNames()->first() ?? 'pea_admin',
            action: 'school_request.approve',
            entityType: 'school_request',
            entityId: $request->id,
            before: ['status' => 'pending'],
            after: $request->fresh()->toArray(),
        );

        return $request->fresh();
    }
}
