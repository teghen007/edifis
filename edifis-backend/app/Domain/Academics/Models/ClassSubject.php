<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A subject offered by a class, with a class-specific code (e.g. "GEO 1" for
 * Form 1 Geography, "GEO US" for Upper Sixth). Assigning at the class level
 * cascades the subject down to every section (stream) of that class.
 */
class ClassSubject extends Model
{
    use HasUuids;

    protected $table = 'class_subject';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'class_id',
        'subject_id',
        'code',
        'coefficient',
        'is_core',
    ];

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
            'is_core' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $cs) => $cs->cascadeToStreams());
        static::deleted(fn (self $cs) => $cs->removeFromStreams());
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /** Ensure every section of this class offers the subject. */
    public function cascadeToStreams(): void
    {
        foreach (DB::table('streams')->where('class_id', $this->class_id)->pluck('id') as $streamId) {
            DB::table('subject_stream')->updateOrInsert(
                ['stream_id' => $streamId, 'subject_id' => $this->subject_id],
                ['coefficient' => $this->coefficient, 'updated_at' => now()],
            );
        }
    }

    /** Drop the subject from every section of this class. */
    public function removeFromStreams(): void
    {
        $streamIds = DB::table('streams')->where('class_id', $this->class_id)->pluck('id');
        DB::table('subject_stream')
            ->whereIn('stream_id', $streamIds)
            ->where('subject_id', $this->subject_id)
            ->delete();
    }
}
