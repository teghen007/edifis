<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Ledger\Models\LedgerEntry;

class BalanceQuery
{
    /** Balance = SUM(amount) in CFA minor units. Derived, never stored. */
    public function get(string $studentId): array
    {
        $balance = (int) LedgerEntry::where('student_id', $studentId)->sum('amount');

        return [
            'student_id' => $studentId,
            'balance' => (int) $balance,
            'currency' => 'XAF',
        ];
    }
}
