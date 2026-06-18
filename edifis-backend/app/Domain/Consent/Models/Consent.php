<?php

declare(strict_types=1);

namespace App\Domain\Consent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Consent extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'student_id',
        'consenter_name',
        'relationship',
        'consenter_contact',
        'consented_at',
        'scope',
        'version',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'revoked_at' => 'datetime',
            'scope' => 'array',
        ];
    }
}
