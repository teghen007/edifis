<?php

declare(strict_types=1);

namespace App\Domain\Students\Models;

use App\Domain\Academics\Models\SchoolClass;
use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'boarding_status',
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

    /** The student's current section (authoritative single link). */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class, 'stream_id');
    }

    public function streams(): BelongsToMany
    {
        return $this->belongsToMany(Stream::class, 'student_stream', 'student_id', 'stream_id')
            ->withTimestamps();
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'student_subject', 'student_id', 'subject_id')
            ->withTimestamps();
    }
}
