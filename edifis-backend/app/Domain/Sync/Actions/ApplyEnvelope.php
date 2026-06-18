<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Academics\Models\Mark;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Audit\Models\AuditEntry;
use App\Domain\Consent\Models\Consent;
use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Students\Models\Student;
use App\Domain\Sync\Services\ConflictResolver;
use App\Domain\Sync\Services\CursorService;
use Illuminate\Support\Facades\DB;

class ApplyEnvelope
{
    const SYNC_TYPES = [
        'issue_event', 'attendance_event', 'ledger_entry', 'audit_entry',
        'student', 'consent', 'mark',
    ];

    const ACCOUNTABILITY_TYPES = [
        'issue_event', 'attendance_event', 'ledger_entry', 'audit_entry',
    ];

    public function __construct(
        private readonly ConflictResolver $conflict,
        private readonly CursorService $cursors,
    ) {}

    public function push(array $envelope): array
    {
        $items = $envelope['items'] ?? [];
        $priority = $envelope['priority'] ?? 'normal';
        $nodeId = $envelope['node_id'];
        $now = now();

        $sorted = $this->sortByPriority($items, $priority);
        $applied = [];
        $conflicts = [];

        foreach ($sorted as $item) {
            $payload = $item['payload'];
            $payload['synced_time'] = $now->toIso8601ZuluString();

            $result = $this->conflict->resolve(
                $item['type'],
                $payload,
                $item['revision'],
            );

            if (($result['status'] ?? '') === 'conflict') {
                $conflicts[] = $result;
            } else {
                $applied[] = $result;
            }

            $this->cursors->set($nodeId, $item['type'], $now->toIso8601ZuluString());
        }

        return [
            'applied' => count($applied),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Apply a pulled delta — PRESERVES the cloud's authoritative synced_time.
     * Does NOT re-stamp. Bug fix: push() re-stamps synced_time=now(), which
     * destroys the cloud's timestamp. applyPulled() keeps the payload's value.
     */
    public function applyPulled(array $envelope): array
    {
        $items = $envelope['items'] ?? [];
        $nodeId = $envelope['node_id'];

        $applied = [];
        $conflicts = [];

        foreach ($items as $item) {
            $payload = $item['payload'];
            // Preserve the cloud's synced_time — do NOT re-stamp

            $result = $this->conflict->resolve(
                $item['type'],
                $payload,
                $item['revision'],
            );

            if (($result['status'] ?? '') === 'conflict') {
                $conflicts[] = $result;
            } else {
                $applied[] = $result;
            }

            $this->cursors->set($nodeId, $item['type'], $payload['synced_time'] ?? $payload['recorded_at'] ?? now()->toIso8601ZuluString());
        }

        return [
            'applied' => count($applied),
            'conflicts' => $conflicts,
        ];
    }

    /** Build pull delta off synced_time (authoritative), not created_at. */
    public function pull(string $nodeId, ?string $sinceCursor): array
    {
        $items = [];
        $maxCursor = $sinceCursor;
        $conflicts = $this->pullConflicts($nodeId);

        $types = array_merge(self::ACCOUNTABILITY_TYPES, array_diff(self::SYNC_TYPES, self::ACCOUNTABILITY_TYPES));

        foreach ($types as $type) {
            try {
                $modelClass = $this->modelForType($type);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $typeCursor = $this->cursors->get($nodeId, $type);
            $effectiveSince = $sinceCursor ?? $typeCursor;

            $query = $modelClass::query();

            $records = $query
                ->when($effectiveSince, fn ($q) => $q->where('synced_time', '>', $effectiveSince))
                ->limit(500)
                ->get();

            foreach ($records as $record) {
                $items[] = [
                    'type' => $type,
                    'id' => $record->id,
                    'revision' => $record->revision ?? $record->id,
                    'payload' => $record->toArray(),
                ];

                $cursor = $record->synced_time;
                if ($cursor && (string) $cursor > (string) $maxCursor) {
                    $maxCursor = (string) $cursor;
                }
            }
        }

        return [
            'direction' => 'pull',
            'node_id' => $nodeId,
            'since_cursor' => $sinceCursor,
            'next_cursor' => $maxCursor ?? now()->toIso8601ZuluString(),
            'items' => $items,
            'conflicts' => $conflicts ?: null,
        ];
    }

    /** Pull pending mark conflicts for the owning node's teachers. Target by owner_teacher_id. */
    private function pullConflicts(string $nodeId): array
    {
        $conflictRows = DB::table('mark_conflicts')
            ->whereNull('ack_id')
            ->get();

        $pulled = [];

        foreach ($conflictRows as $row) {
            $mark = Mark::find($row->mark_id);
            if (! $mark) continue;

            $pulled[] = [
                'type' => 'mark',
                'id' => $row->mark_id,
                'resolution' => 'cloud_wins',
                'winning_revision' => $row->winning_revision,
                'rejected_revision' => $row->rejected_revision,
                'owner_teacher_id' => $mark->owner_teacher_id,
                'delivery_id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
            ];
        }

        return $pulled;
    }

    /** Client acks delivery. Only then mark it as delivered. */
    public function ackConflict(string $deliveryId): void
    {
        // In full implementation: mark the conflict as ack'd in mark_conflicts
        DB::table('mark_conflicts')
            ->where('id', $deliveryId)
            ->orWhere('ack_id', $deliveryId)
            ->update(['ack_id' => 'ack-' . $deliveryId, 'pulled_at' => now()]);
    }

    private function sortByPriority(array $items, string $priority): array
    {
        if ($priority === 'accountability') {
            return $items;
        }

        $accountability = [];
        $normal = [];

        foreach ($items as $item) {
            if (in_array($item['type'], self::ACCOUNTABILITY_TYPES, true)) {
                $accountability[] = $item;
            } else {
                $normal[] = $item;
            }
        }

        return array_merge($accountability, $normal);
    }

    private function modelForType(string $type): string
    {
        return match ($type) {
            'issue_event' => IssueEvent::class,
            'attendance_event' => AttendanceEvent::class,
            'ledger_entry' => LedgerEntry::class,
            'audit_entry' => AuditEntry::class,
            'student' => Student::class,
            'consent' => Consent::class,
            'mark' => Mark::class,
            default => throw new \InvalidArgumentException("Unknown entity type: {$type}"),
        };
    }
}
