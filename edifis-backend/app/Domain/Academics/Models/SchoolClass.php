<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** Short class code suffix used in subject codes: 1..5, LS, US. */
    public function codeSuffix(): string
    {
        return match ($this->name) {
            'Lower Sixth' => 'LS',
            'Upper Sixth' => 'US',
            default => (string) $this->level,
        };
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class, 'class_id');
    }
}
