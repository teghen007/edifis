<?php

declare(strict_types=1);

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Models\Mark;
use App\Domain\Audit\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class RecordMark
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        string $id,
        string $revision,
        ?string $revisionParent,
        string $studentId,
        string $subjectId,
        string $classId,
        string $sequence,
        string $ownerTeacherId,
        float $score,
        float $maxScore,
        ?float $coefficient = null,
        ?string $deviceId = null,
        bool $published = false,
        ?string $reason = null,
        ?string $syncedTime = null,
    ): Mark {
        $existing = Mark::find($id);

        $before = $existing?->toArray();
        $action = $existing ? 'mark.edit' : 'mark.create';

        if ($existing) {
            $existing->update([
                'revision' => $revision,
                'revision_parent' => $revisionParent,
                'score' => $score,
                'max_score' => $maxScore,
                'coefficient' => $this->resolveCoefficient($coefficient, $existing->class_id, $existing->subject_id),
                'recorded_at' => now(),
                'published' => $published,
                'synced_time' => $syncedTime,
            ]);
            $mark = $existing->fresh();
        } else {
            $mark = Mark::create([
                'id' => $id,
                'revision' => $revision,
                'revision_parent' => $revisionParent,
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'class_id' => $classId,
                'sequence' => $sequence,
                'owner_teacher_id' => $ownerTeacherId,
                'score' => $score,
                'max_score' => $maxScore,
                'coefficient' => $this->resolveCoefficient($coefficient, $classId, $subjectId),
                'recorded_at' => now(),
                'published' => $published,
                'synced_time' => $syncedTime,
            ]);
        }

        $this->audit->log(
            actorId: $ownerTeacherId,
            action: $action,
            entityType: 'mark',
            entityId: $id,
            before: $before,
            after: $mark->toArray(),
            deviceId: $deviceId,
        );

        if ($reason) {
            $this->audit->log(
                actorId: $ownerTeacherId,
                action: $action . '.reason',
                entityType: 'mark',
                entityId: $id,
                before: null,
                after: ['reason' => $reason],
            );
        }

        return $mark;
    }

    /**
     * The weighting that report cards use. Prefer an explicit value, else the
     * class-specific coefficient (class_subject), else the subject's base
     * coefficient, else 1.
     */
    private function resolveCoefficient(?float $coefficient, ?string $classId, ?string $subjectId): float
    {
        if ($coefficient !== null) {
            return $coefficient;
        }

        if ($classId && $subjectId) {
            $classCoef = DB::table('class_subject')
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->value('coefficient');
            if ($classCoef !== null) {
                return (float) $classCoef;
            }
        }

        if ($subjectId) {
            $subjectCoef = DB::table('subjects')->where('id', $subjectId)->value('coefficient');
            if ($subjectCoef !== null) {
                return (float) $subjectCoef;
            }
        }

        return 1.0;
    }
}
