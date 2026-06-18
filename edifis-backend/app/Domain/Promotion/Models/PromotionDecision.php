<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionDecision extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'student_id',
        'academic_year',
        'yearly_average',
        'outcome',
        'ruleset_version',
        'pathway',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'yearly_average' => 'float',
            'computed_at' => 'datetime',
        ];
    }
}
