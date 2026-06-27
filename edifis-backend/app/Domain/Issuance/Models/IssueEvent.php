<?php

declare(strict_types=1);

namespace App\Domain\Issuance\Models;

use App\Domain\Students\Models\Student;
use App\Support\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueEvent extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'id',
        'revision',
        'student_id',
        'catalogue_item_id',
        'cost',
        'issued_at',
        'synced_time',
        'staff_id',
        'signature_ref',
        'batch_id',
        'device_id',
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'issued_at' => 'datetime',
            'synced_time' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function catalogueItem(): BelongsTo
    {
        return $this->belongsTo(CatalogueItem::class, 'catalogue_item_id');
    }
}
