<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Academics\Models\Stream;
use App\Domain\School\Models\SchoolSetting;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Blank student-admission template. One row per student; the importer
 * ({@see \App\Imports\StudentAdmissionImport}) creates the student, places
 * them in the section, and creates/links the phone-keyed guardian account.
 */
class StudentAdmissionTemplate implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    public const HEADERS = [
        'Given name', 'Family name', 'Other names', 'Sex (M/F)',
        'Date of birth (YYYY-MM-DD)', 'Section', 'Boarding (day/boarding)',
        'Guardian name', 'Guardian phone', 'Relationship',
    ];

    public function title(): string
    {
        return 'Admissions';
    }

    public function array(): array
    {
        $school = SchoolSetting::current()->name ?: 'School';
        $sections = Stream::with('schoolClass')->where('active', true)->orderBy('name')->get()
            ->map(fn ($s) => $s->name)->implode(', ');

        $rows = [
            [$school],
            ['STUDENT ADMISSION — one row per student. Valid sections: ' . $sections],
            self::HEADERS,
        ];

        // 25 blank rows to type into.
        for ($i = 0; $i < 25; $i++) {
            $rows[] = array_fill(0, count(self::HEADERS), '');
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:J1');
                $sheet->mergeCells('A2:J2');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
                $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('64748B');

                $header = $sheet->getStyle('A3:J3');
                $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');

                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(18);
                }
            },
        ];
    }
}
