<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class EdifisTenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'school_code',
            'school_name',
            'school_location',
        ];
    }

    protected $fillable = [
        'id',
        'school_code',
        'school_name',
        'school_location',
    ];
}
