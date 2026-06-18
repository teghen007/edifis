<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'id',
        'title',
        'type',
        'starts_at',
        'ends_at',
        'scope',
        'class_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
