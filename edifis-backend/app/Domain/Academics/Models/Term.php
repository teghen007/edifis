<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    use HasUuids;

    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    /** Sequences (evaluations) per term — Cameroon norm: 2 per term, 6 per year. */
    public const SEQUENCES_PER_TERM = 2;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'academic_year_id',
        'position',
        'status',
        'current_sequence',
        'starts_on',
        'ends_on',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'current_sequence' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isOpenForEntry(): bool
    {
        // Soft model: anything not explicitly closed accepts marks.
        return $this->status !== self::STATUS_CLOSED;
    }

    /**
     * The app records marks against a global sequence number 1..6.
     * Map this term's local sequence (1 or 2) to that global number.
     * e.g. Term 2, local sequence 2 -> (2-1)*2 + 2 = 4.
     */
    public function globalSequence(?int $localSequence = null): int
    {
        $local = $localSequence ?? $this->current_sequence ?? 1;

        return (($this->position - 1) * self::SEQUENCES_PER_TERM) + $local;
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }
}
