<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class AttendanceEvent extends Model
{
    use HasUuidV7;

    protected $table = 'attendance_events';

    protected $fillable = [
        'id',
        'revision',
        'session_id',
        'student_id',
        'scanned_at',
        'synced_time',
        'device_id',
        'scanned_by',
        'source',
        'status',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'synced_time' => 'datetime',
        ];
    }
}
