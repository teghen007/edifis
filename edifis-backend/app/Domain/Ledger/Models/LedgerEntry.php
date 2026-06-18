<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'id',
        'student_id',
        'source_event_id',
        'amount',
        'posted_at',
        'synced_time',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'posted_at' => 'datetime',
            'synced_time' => 'datetime',
        ];
    }
}
