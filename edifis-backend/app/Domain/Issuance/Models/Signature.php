<?php

declare(strict_types=1);

namespace App\Domain\Issuance\Models;

use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'batch_id', 'staff_id', 'image_data', 'mime_type', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }
}
