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
 * Column layout (0-indexed): 0=S/N, 1=Student ID, 2=name, 3=class, 4=balance, 5=charge, 6=payment.
 * Header row + data start are located by content (robust to title/styling rows).
 */
class FeesSheetImport implements \Maatwebsite\Excel\Concerns\ToCollection
{
    private const ID_COL = 1;
    private const CHARGE_COL = 5;
    private const PAYMENT_COL = 6;

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

        $start = $this->dataStartRow($rows);

        for ($i = $start; $i < $rows->count(); $i++) {
            $row = $rows[$i];
            $studentId = trim((string) ($row[self::ID_COL] ?? ''));
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

    /** First data row index = the row after the header (the one containing "Student ID"). */
    private function dataStartRow(Collection $rows): int
    {
        for ($i = 0; $i < $rows->count(); $i++) {
            foreach ($rows[$i] as $cell) {
                if (trim((string) $cell) === 'Student ID') {
                    return $i + 1;
                }
            }
        }

        return 4; // default: header on row 4 (index 3), data from index 4
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
