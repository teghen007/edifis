<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionOverride extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'decision_id',
        'old_outcome',
        'new_outcome',
        'reason',
        'principal_id',
        'overridden_at',
    ];

    protected function casts(): array
    {
        return [
            'overridden_at' => 'datetime',
        ];
    }
}
