<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\Students\Models\Student;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Fees worksheet for the bursar.
 *
 * Layout:
 *   Row 1  → meta: A="meta_kind", B="fees"
 *   Row 2  → header: Student ID | Student Name | Class | Current Balance (XAF) | Charge (XAF) | Payment (XAF)
 *   Row 3+ → data: id, name, class, balance, <blank>, <blank>
 *
 * The bursar fills "Charge" to bill a student and/or "Payment" to record money collected.
 */
class FeesSheetExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    public function __construct(private readonly ?string $classId = null) {}

    public function title(): string
    {
        return 'Fees';
    }

    public function array(): array
    {
        $query = app(BalanceQuery::class);

        $students = Student::where('active', true)
            ->when($this->classId, fn ($q) => $q->where('current_class_id', $this->classId))
            ->with('schoolClass')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => trim($s->given_name . ' ' . $s->family_name),
                'class' => $s->schoolClass?->name ?? '',
                'balance' => $query->get($s->id)['balance'],
            ])
            ->sortBy('name')
            ->values();

        $rows = [];
        $rows[] = ['meta_kind', 'fees'];
        $rows[] = ['Student ID', 'Student Name', 'Class', 'Current Balance (XAF)', 'Charge (XAF)', 'Payment (XAF)'];

        foreach ($students as $s) {
            $rows[] = [$s['id'], $s['name'], $s['class'], $s['balance'], '', ''];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:B1')->getFont()->getColor()->setRGB('999999');
                $sheet->getStyle('A2:F2')->getFont()->setBold(true);

                $sheet->setCellValue('H1', 'Fill "Charge" to bill a student, "Payment" to record money collected. Do not edit rows 1-2 or the ID column.');
                $sheet->getStyle('H1')->getFont()->setItalic(true)->getColor()->setRGB('999999');

                foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
