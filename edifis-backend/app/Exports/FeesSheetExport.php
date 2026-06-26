<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\School\Models\SchoolSetting;
use App\Domain\Students\Models\Student;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Branded fees worksheet for the bursar.
 *
 *   Row 1  Title (school name)        Row 2  FEES SHEET
 *   Row 4  Header: S/N | Student ID | Student Name | Class | Current Balance | Charge | Payment
 *   Row 5+ Data
 *
 * The bursar fills "Charge" to bill and/or "Payment" to record money collected.
 * Student ID column is hidden; the import locates the header + columns by content.
 */
class FeesSheetExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private int $lastRow = 4;

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
        $rows[] = [SchoolSetting::schoolName()];                              // 1
        $rows[] = ['FEES SHEET'];                                             // 2
        $rows[] = [];                                                         // 3
        $rows[] = ['S/N', 'Student ID', 'Student Name', 'Class', 'Current Balance (XAF)', 'Charge (XAF)', 'Payment (XAF)']; // 4

        $n = 1;
        foreach ($students as $s) {
            $rows[] = [$n++, $s['id'], $s['name'], $s['class'], $s['balance'], '', ''];
        }

        $this->lastRow = 4 + $students->count();

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('A2:G2');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('1E3A8A');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('2563EB');
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle('A4:G4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A4:G4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
                $sheet->getStyle('A4:G4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

                if ($this->lastRow >= 5) {
                    $range = 'A4:G' . $this->lastRow;
                    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
                    $sheet->getStyle('A4:A' . $this->lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('E5:G' . $this->lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    for ($i = 5; $i <= $this->lastRow; $i++) {
                        if ($i % 2 === 1) {
                            $sheet->getStyle("A{$i}:G{$i}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                        }
                    }
                }

                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setVisible(false);   // Student ID
                $sheet->getColumnDimension('C')->setWidth(30);
                $sheet->getColumnDimension('D')->setWidth(14);
                $sheet->getColumnDimension('E')->setWidth(18);
                $sheet->getColumnDimension('F')->setWidth(14);
                $sheet->getColumnDimension('G')->setWidth(14);
                $sheet->freezePane('A5');
            },
        ];
    }
}
