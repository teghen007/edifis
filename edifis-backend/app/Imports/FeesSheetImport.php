<?php

declare(strict_types=1);

namespace App\Imports;

use App\Domain\Ledger\Actions\PostLedgerDebit;
use App\Domain\Students\Models\Student;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

/**
 * Reads the worksheet produced by {@see \App\Exports\FeesSheetExport} and posts
 * ledger entries: positive "Charge" amounts as debits, "Payment" amounts as credits.
 *
 * Column layout (0-indexed): 0=id, 1=name, 2=class, 3=balance, 4=charge, 5=payment.
 */
class FeesSheetImport implements \Maatwebsite\Excel\Concerns\ToCollection
{
    private const CHARGE_COL = 4;
    private const PAYMENT_COL = 5;

    private array $result = [
        'charged_count' => 0,
        'charged_total' => 0,
        'collected_count' => 0,
        'collected_total' => 0,
        'errors' => [],
    ];

    public function collection(Collection $rows): void
    {
        $ledger = app(PostLedgerDebit::class);

        // Data starts at row index 2 (after meta + header).
        for ($i = 2; $i < $rows->count(); $i++) {
            $row = $rows[$i];
            $studentId = trim((string) ($row[0] ?? ''));
            if ($studentId === '') {
                continue;
            }

            $charge = $this->amount($row[self::CHARGE_COL] ?? null);
            $payment = $this->amount($row[self::PAYMENT_COL] ?? null);

            if ($charge === null && $payment === null) {
                continue; // nothing to post on this row
            }

            if ($charge === false || $payment === false) {
                $this->result['errors'][] = ['row' => $i + 1, 'reason' => 'Charge/Payment must be a positive whole number.'];
                continue;
            }

            if (!Student::whereKey($studentId)->where('active', true)->exists()) {
                $this->result['errors'][] = ['row' => $i + 1, 'reason' => 'Unknown or inactive student.'];
                continue;
            }

            if ($charge) {
                $ledger->debit($studentId, $charge, (string) Uuid::uuid7());
                $this->result['charged_count']++;
                $this->result['charged_total'] += $charge;
            }

            if ($payment) {
                $ledger->credit($studentId, $payment, (string) Uuid::uuid7());
                $this->result['collected_count']++;
                $this->result['collected_total'] += $payment;
            }
        }
    }

    /**
     * @return int|null|false  int amount (>0), null if blank, false if invalid
     */
    private function amount(mixed $value): int|null|false
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        // Allow "25,000" or "25000.00"
        $clean = str_replace([',', ' '], '', $raw);
        if (!is_numeric($clean)) {
            return false;
        }
        $amount = (int) round((float) $clean);
        return $amount > 0 ? $amount : false;
    }

    public function getResult(): array
    {
        return $this->result;
    }
}
