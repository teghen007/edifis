<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasUuidV7;

    protected $table = 'attendance_sessions';

    protected $fillable = [
        'id',
        'class_id',
        'stream_id',
        'attendance_date',
        'subject_id',
        'teacher_id',
        'period',
        'mode',
        'headcount',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'headcount' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class, 'session_id');
    }
}
