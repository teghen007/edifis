<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use App\Domain\Academics\Actions\RecordMark;
use App\Domain\Academics\Models\Mark;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Students\Models\Student;
use App\Domain\Consent\Models\Consent;
use App\Domain\Audit\Models\AuditEntry;
use App\Support\Idempotency;
use Illuminate\Support\Facades\DB;

class ConflictResolver
{
    const APPEND_ONLY = ['issue_event', 'attendance_event', 'ledger_entry', 'audit_entry'];
    const LWW = ['student'];
    const VERSIONED = ['consent'];
    const MARK = ['mark'];

    public function __construct(
        private readonly RecordMark $recordMark,
        private readonly AuditLogger $audit,
    ) {}

    public function resolve(string $type, array $payload, string $revision): array
    {
        return match (true) {
            in_array($type, self::APPEND_ONLY, true) => $this->resolveAppendOnly($type, $payload, $revision),
            in_array($type, self::LWW, true) => $this->resolveLww($type, $payload, $revision),
            in_array($type, self::VERSIONED, true) => $this->resolveVersioned($type, $payload, $revision),
            in_array($type, self::MARK, true) => $this->resolveMark($type, $payload, $revision),
            default => throw new \InvalidArgumentException("Unknown entity type: {$type}"),
        };
    }

    private function resolveAppendOnly(string $type, array $payload, string $revision): array
    {
        return Idempotency::applyOnce($payload['id'], $revision, function () use ($type, $payload) {
            $model = $this->modelForType($type);
            $model::create($payload);
            return ['status' => 'applied', 'type' => $type, 'id' => $payload['id']];
        });
    }

    private function resolveLww(string $type, array $payload, string $revision): array
    {
        $model = $this->modelForType($type);
        $existing = $model::find($payload['id']);

        if (! $existing) {
            $model::create($payload);
            return ['status' => 'applied', 'type' => $type, 'id' => $payload['id']];
        }

        if (($payload['demographics_revision'] ?? '') > ($existing->demographics_revision ?? '')) {
            $existing->update($payload);
            return ['status' => 'applied', 'type' => $type, 'id' => $payload['id']];
        }

        return ['status' => 'replay', 'type' => $type, 'id' => $payload['id']];
    }

    private function resolveVersioned(string $type, array $payload, string $revision): array
    {
        $model = $this->modelForType($type);
        $existing = $model::where('student_id', $payload['student_id'])
            ->where('version', $payload['version'])
            ->first();

        if ($existing) {
            return ['status' => 'replay', 'type' => $type, 'id' => $payload['id']];
        }

        $model::create($payload);
        return ['status' => 'applied', 'type' => $type, 'id' => $payload['id']];
    }

    /**
     * Mark resolution — per-record teacher ownership. ADR-008.
     * 1. Idempotent replay: current.revision === payload.revision → replay
     * 2. Linear edit: revision_parent matches current.revision → apply via RecordMark
     * 3. True divergent conflict: cloud-wins → persist conflict + audit (idempotent on mark_id+rejected_revision)
     */
    private function resolveMark(string $type, array $payload, string $revision): array
    {
        $current = Mark::find($payload['id']);

        if (! $current) {
            $this->recordMark->handle(
                id: $payload['id'],
                revision: $payload['revision'],
                revisionParent: $payload['revision_parent'] ?? null,
                studentId: $payload['student_id'],
                subjectId: $payload['subject_id'],
                classId: $payload['class_id'],
                sequence: $payload['sequence'],
                ownerTeacherId: $payload['owner_teacher_id'],
                score: (float) $payload['score'],
                maxScore: (float) $payload['max_score'],
                coefficient: isset($payload['coefficient']) ? (float) $payload['coefficient'] : null,
                published: $payload['published'] ?? false,
                syncedTime: $payload['synced_time'] ?? null,
            );
            return ['status' => 'applied', 'type' => $type, 'id' => $payload['id']];
        }

        if ($current->revision === $payload['revision']) {
            return ['status' => 'replay', 'type' => $type, 'id' => $payload['id']];
        }

        if (($payload['revision_parent'] ?? null) === $current->revision) {
            $this->recordMark->handle(
                id: $payload['id'],
                revision: $payload['revision'],
                revisionParent: $payload['revision_parent'] ?? null,
                studentId: $payload['student_id'],
                subjectId: $payload['subject_id'],
                classId: $payload['class_id'],
                sequence: $payload['sequence'],
                ownerTeacherId: $payload['owner_teacher_id'],
                score: (float) $payload['score'],
                maxScore: (float) $payload['max_score'],
                coefficient: isset($payload['coefficient']) ? (float) $payload['coefficient'] : null,
                published: $payload['published'] ?? false,
                syncedTime: $payload['synced_time'] ?? null,
            );
            return ['status' => 'applied', 'type' => $type, 'id' => $payload['id']];
        }

        // True divergent conflict — idempotent per (mark_id, rejected_revision)
        $rejectedRevision = $payload['revision'];
        $winningRevision = $current->revision;

        $conflictKey = $payload['id'] . '-' . $rejectedRevision;
        $alreadyResolved = DB::table('mark_conflicts')
            ->where('mark_id', $payload['id'])
            ->where('rejected_revision', $rejectedRevision)
            ->exists();

        if (! $alreadyResolved) {
            DB::table('mark_conflicts')->insert([
                'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
                'mark_id' => $payload['id'],
                'winning_revision' => $winningRevision,
                'rejected_revision' => $rejectedRevision,
                'resolved_at' => now(),
            ]);

            $this->audit->log(
                actorId: '00000000-0000-0000-0000-000000000001',
                action: 'mark.conflict',
                entityType: 'mark',
                entityId: $payload['id'],
                before: $payload,
                after: $current->toArray(),
            );
        }

        return [
            'status' => 'conflict',
            'type' => $type,
            'id' => $payload['id'],
            'resolution' => 'cloud_wins',
            'winning_revision' => $winningRevision,
            'rejected_revision' => $rejectedRevision,
        ];
    }

    private function modelForType(string $type): string
    {
        return match ($type) {
            'issue_event' => IssueEvent::class,
            'attendance_event' => AttendanceEvent::class,
            'ledger_entry' => LedgerEntry::class,
            'student' => Student::class,
            'consent' => Consent::class,
            'audit_entry' => AuditEntry::class,
            'mark' => Mark::class,
            default => throw new \InvalidArgumentException("Unknown entity type: {$type}"),
        };
    }
}
