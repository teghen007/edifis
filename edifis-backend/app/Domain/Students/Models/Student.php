<?php

declare(strict_types=1);

namespace App\Domain\Students\Models;

use App\Domain\Academics\Models\SchoolClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'master_pea_id',
        'given_name',
        'family_name',
        'other_names',
        'sex',
        'date_of_birth',
        'current_class_id',
        'class_id',
        'photo_ref',
        'enrolled_at',
        'demographics_revision',
        'active',
        'synced_time',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'enrolled_at' => 'datetime',
            'active' => 'boolean',
            'synced_time' => 'datetime',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
