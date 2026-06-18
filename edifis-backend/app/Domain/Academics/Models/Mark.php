<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

/** Stub — fully implemented in Phase 4 (Academics). */
class Mark extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'id',
        'revision',
        'revision_parent',
        'student_id',
        'subject_id',
        'class_id',
        'sequence',
        'owner_teacher_id',
        'score',
        'max_score',
        'coefficient',
        'recorded_at',
        'synced_time',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'max_score' => 'float',
            'coefficient' => 'float',
            'recorded_at' => 'datetime',
            'synced_time' => 'datetime',
            'published' => 'boolean',
        ];
    }
}
