<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasUuids;

    protected $table = 'school_classes';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'level',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'active' => 'boolean',
        ];
    }
}
