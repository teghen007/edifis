<?php

declare(strict_types=1);

namespace App\Domain\Issuance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Support\Idempotency;
use Ramsey\Uuid\Uuid;

class IssueItemsToStudent
{
    public function __construct(
        private readonly \App\Domain\Issuance\Repositories\IssueEventRepository $repo,
        private readonly \App\Domain\Ledger\Actions\PostLedgerDebit $postLedger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(
        string $batchId,
        string $studentId,
        array $items,
        string $signatureRef,
        string $staffId,
        ?string $deviceId = null,
    ): array {
        $revision = $batchId; // Stable per batch — idempotency on batch_id

        return Idempotency::applyOnce($batchId, $revision, function () use (
            $batchId, $studentId, $items, $signatureRef, $staffId, $deviceId, $revision
        ) {
            $events = [];
            $ledgerEntries = [];
            $now = now();

            foreach ($items as $item) {
                $catalogueId = $item['catalogue_item_id'];
                $catalogue = CatalogueItem::findOrFail($catalogueId);

                $event = $this->repo->append([
                    'id' => (string) Uuid::uuid7(),
                    'revision' => $revision,
                    'student_id' => $studentId,
                    'catalogue_item_id' => $catalogueId,
                    'cost' => $catalogue->cost,
                    'issued_at' => $now,
                    'staff_id' => $staffId,
                    'signature_ref' => $signatureRef,
                    'batch_id' => $batchId,
                    'device_id' => $deviceId,
                    'status' => 'issued',
                ]);

                $events[] = $event;

                $debit = $this->postLedger->debit(
                    studentId: $studentId,
                    amount: $catalogue->cost,
                    sourceEventId: $event->id,
                );

                $ledgerEntries[] = $debit;

                $this->audit->log(
                    actorId: $staffId,
                    action: 'issue.create',
                    entityType: 'issue_event',
                    entityId: $event->id,
                    after: $event->toArray(),
                    deviceId: $deviceId,
                );
            }

            return [
                'events' => $events,
                'posted' => $ledgerEntries,
            ];
        });
    }
}
