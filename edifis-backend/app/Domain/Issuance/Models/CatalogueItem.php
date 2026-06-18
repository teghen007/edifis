<?php

declare(strict_types=1);

namespace App\Domain\Issuance\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class CatalogueItem extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'id',
        'name',
        'description',
        'cost',
        'category',
        'default_for_forms',
        'isbn',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'default_for_forms' => 'array',
            'active' => 'boolean',
        ];
    }
}
