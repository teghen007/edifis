<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    use HasUuidV7;

    protected $table = 'attendance_sessions';

    protected $fillable = [
        'id',
        'class_id',
        'subject_id',
        'teacher_id',
        'period',
        'headcount',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'headcount' => 'integer',
        ];
    }
}
