<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasUuids;

    protected $table = 'subjects';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'code',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
