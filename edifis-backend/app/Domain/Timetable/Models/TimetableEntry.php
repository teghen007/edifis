<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Models;

use App\Domain\Academics\Models\SchoolClass;
use App\Domain\Academics\Models\Subject;
use App\Models\User;
use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    use HasUuidV7;

    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    protected $fillable = [
        'id',
        'class_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'period_start',
        'period_end',
        'room',
        'created_by',
        'approved_by',
        'approved_at',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function dayName(): string
    {
        return self::DAYS[(int) $this->day_of_week] ?? (string) $this->day_of_week;
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
