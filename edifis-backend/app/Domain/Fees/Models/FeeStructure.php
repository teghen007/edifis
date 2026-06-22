<?php

declare(strict_types=1);

namespace App\Domain\Fees\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    use HasUuids;

    protected $table = 'fee_structures';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'class_id', 'name', 'amount', 'applies_to', 'academic_year_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public const APPLIES = ['all' => 'All students', 'day' => 'Day students', 'boarding' => 'Boarding students'];
}
