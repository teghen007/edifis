<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Timetable\Models\TimetableEntry;

class ApproveTimetable
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(string $entryId, string $principalId): TimetableEntry
    {
        $entry = TimetableEntry::findOrFail($entryId);

        $entry->update([
            'is_approved' => true,
            'approved_by' => $principalId,
            'approved_at' => now(),
        ]);

        $this->audit->log(
            actorId: $principalId,
            action: 'timetable.approve',
            entityType: 'timetable_entry',
            entityId: $entryId,
            before: ['is_approved' => false],
            after: ['is_approved' => true],
        );

        return $entry->fresh();
    }
}
