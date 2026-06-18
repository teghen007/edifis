<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'device_token', 'device_name', 'trusted_until',
    ];

    protected function casts(): array
    {
        return [
            'trusted_until' => 'datetime',
        ];
    }
}
