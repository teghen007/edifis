<?php

declare(strict_types=1);

namespace App\Domain\Issuance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Issuance\Models\IssueEvent;
use Ramsey\Uuid\Uuid;

class ReturnItem
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly \App\Domain\Ledger\Actions\PostLedgerDebit $postLedger,
    ) {}

    public function handle(string $issueEventId, string $reason, string $staffId): array
    {
        $original = IssueEvent::findOrFail($issueEventId);

        if ($original->status === 'returned') {
            return ['status' => 'already_returned', 'event' => $original];
        }

        $returnEvent = IssueEvent::create([
            'id' => (string) Uuid::uuid7(),
            'revision' => ($original->revision ?? '') . '-return',
            'student_id' => $original->student_id,
            'catalogue_item_id' => $original->catalogue_item_id,
            'cost' => $original->cost,
            'issued_at' => now(),
            'staff_id' => $staffId,
            'signature_ref' => $original->signature_ref,
            'batch_id' => $original->batch_id,
            'device_id' => $original->device_id,
            'status' => 'returned',
            'reason' => $reason,
        ]);

        $credit = $this->postLedger->credit(
            studentId: $original->student_id,
            amount: $original->cost,
            sourceEventId: $returnEvent->id,
        );

        $this->audit->log(
            actorId: $staffId,
            action: 'issue.return',
            entityType: 'issue_event',
            entityId: $returnEvent->id,
            before: ['original_event_id' => $issueEventId],
            after: $returnEvent->toArray(),
        );

        return ['event' => $returnEvent, 'credit' => $credit];
    }
}
