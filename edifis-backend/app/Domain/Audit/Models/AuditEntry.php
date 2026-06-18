<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class AuditEntry extends Model
{
    use HasUuidV7;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'actor_id',
        'actor_role',
        'action',
        'entity_type',
        'entity_id',
        'before',
        'after',
        'device_id',
        'occurred_at',
        'synced_time',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'occurred_at' => 'datetime',
            'synced_time' => 'datetime',
        ];
    }
}
