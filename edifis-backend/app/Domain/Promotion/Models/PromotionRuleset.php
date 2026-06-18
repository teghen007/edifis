<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionRuleset extends Model
{
    protected $keyType = 'string';
    protected $primaryKey = 'version';
    public $incrementing = false;

    protected $fillable = [
        'version',
        'baseline',
        'coefficients',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'baseline' => 'float',
            'coefficients' => 'array',
            'active' => 'boolean',
        ];
    }
}
