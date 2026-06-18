<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Support\Idempotency;
use Ramsey\Uuid\Uuid;

class PostLedgerDebit
{
    /** Post a positive debit (charge) to the ledger. */
    public function debit(string $studentId, int $amount, string $sourceEventId): LedgerEntry
    {
        return LedgerEntry::create([
            'id' => (string) Uuid::uuid7(),
            'student_id' => $studentId,
            'source_event_id' => $sourceEventId,
            'amount' => $amount,
            'posted_at' => now(),
        ]);
    }

    /** Post a negative credit (return/payment) to the ledger. */
    public function credit(string $studentId, int $amount, string $sourceEventId): LedgerEntry
    {
        return LedgerEntry::create([
            'id' => (string) Uuid::uuid7(),
            'student_id' => $studentId,
            'source_event_id' => $sourceEventId,
            'amount' => -$amount,
            'posted_at' => now(),
        ]);
    }
}
