<?php

declare(strict_types=1);

namespace App\Domain\Conduct\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConductRecord extends Model
{
    use HasUuids;

    protected $table = 'conduct_records';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'student_id', 'term_id', 'stream_id',
        'conduct_grade', 'punctuality', 'comment', 'recorded_by',
    ];

    public const GRADES = ['Excellent', 'Good', 'Fair', 'Poor'];

    public function student(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domain\Students\Models\Student::class, 'student_id');
    }
}
