<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use App\Domain\Tenancy\Models\EdifisTenant;

/** Abstracts tenant context so domain Actions run in both cloud (multi-tenant) and local (single-school) modes. ADR-005. */
class TenantContext
{
    /** Returns the current school ID — real tenant on cloud, the config school_code on node. */
    public function currentSchoolId(): ?string
    {
        if (! ModeGate::tenancyEnabled()) {
            return config('edifis.school_code');
        }

        try {
            $tenant = tenant();
            return $tenant?->id ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Returns the current school code (e.g., 'pssnkwen'). */
    public function currentSchoolCode(): string
    {
        if (! ModeGate::tenancyEnabled()) {
            return config('edifis.school_code', 'pssnkwen');
        }

        try {
            return tenant('id') ?? config('edifis.school_code', 'pssnkwen');
        } catch (\Throwable) {
            return config('edifis.school_code', 'pssnkwen');
        }
    }
}
