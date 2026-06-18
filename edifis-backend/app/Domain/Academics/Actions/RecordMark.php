<?php

declare(strict_types=1);

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Models\Mark;
use App\Domain\Audit\Services\AuditLogger;

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
                'coefficient' => $coefficient,
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
                'coefficient' => $coefficient,
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
}
