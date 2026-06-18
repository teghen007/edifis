<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    use HasUuidV7;

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
}
