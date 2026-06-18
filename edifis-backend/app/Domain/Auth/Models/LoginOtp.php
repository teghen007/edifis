<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class LoginOtp extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'otp_hash', 'expires_at', 'attempts', 'used',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used' => 'boolean',
        ];
    }
}
