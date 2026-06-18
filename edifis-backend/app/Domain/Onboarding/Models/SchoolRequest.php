<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolRequest extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'school_name', 'school_code', 'location',
        'contact_name', 'contact_email', 'contact_phone',
        'estimated_students', 'status', 'approved_by', 'approved_at', 'claim_code',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }
}
