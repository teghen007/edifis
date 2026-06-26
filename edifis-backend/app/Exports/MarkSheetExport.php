<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\Test;
use App\Domain\School\Models\SchoolSetting;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Branded, numbered mark sheet.
 *
 *   Row 1  Title (school name — merged, bold)
 *   Row 2  MARK SHEET subtitle
 *   Row 3  Stream / Subject
 *   Row 4  Test / Max
 *   Row 5  (blank)
 *   Row 6  Header:  S/N | Student ID | Student Name | Marks
 *   Row 7+ Data:    1   | <uuid>     | <name>       | (blank)
 *
 * Columns G/H carry hidden meta (stream_id, subject_id, test_id, max) so the
 * importer is self-describing. The import locates the header row + meta by content.
 */
class MarkSheetExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private int $dataStartRow = 7;
    private int $lastRow = 6;

    public function __construct(
        private readonly string $streamId,
        private readonly string $subjectId,
        private readonly string $testId,
    ) {}

    public function title(): string
    {
        return 'Marks';
    }

    public function array(): array
    {
        $stream = Stream::with('schoolClass')->find($this->streamId);
        $subject = Subject::find($this->subjectId);
        $test = Test::with('term')->find($this->testId);
        $max = $test?->default_max ?? 20;

        $students = DB::table('student_stream')
            ->join('students', 'student_stream.student_id', '=', 'students.id')
            ->join('student_subject', function ($join) {
                $join->on('students.id', '=', 'student_subject.student_id')
                    ->where('student_subject.stream_id', '=', $this->streamId)
                    ->where('student_subject.subject_id', '=', $this->subjectId);
            })
            ->where('student_stream.stream_id', $this->streamId)
            ->select('students.id as student_id', DB::raw("trim(students.given_name || ' ' || students.family_name) as student_name"))
            ->orderBy('student_name')
            ->get();

        $streamLabel = ($stream?->name ?? '') . ($stream?->schoolClass?->name ? ' (' . $stream->schoolClass->name . ')' : '');

        $rows = [];
        $rows[] = [SchoolSetting::schoolName()];                                  // 1
        $rows[] = ['MARK SHEET'];                                                 // 2
        $rows[] = ['Class:', $streamLabel, '', 'Subject:', $subject?->name ?? ''];// 3
        $rows[] = ['Sequence:', ($test?->name ?? ''), '', 'Out of:', $max];      // 4
        $rows[] = [];                                                             // 5
        $rows[] = ['S/N', 'Student ID', 'Student Name', 'Marks'];                // 6 header

        $n = 1;
        foreach ($students as $s) {
            $rows[] = [$n++, $s->student_id, $s->student_name, ''];
        }

        $this->lastRow = 6 + $students->count();

        return $rows;
    }

    public function registerEvents(): array
    {
        $test = Test::find($this->testId);
        $meta = [
            'meta_stream_id' => $this->streamId,
            'meta_subject_id' => $this->subjectId,
            'meta_test_id' => $this->testId,
            'meta_max' => $test?->default_max ?? 20,
        ];

        return [
            AfterSheet::class => function (AfterSheet $event) use ($meta) {
                $sheet = $event->sheet->getDelegate();

                // Hidden self-describing meta in columns G/H.
                $r = 1;
                foreach ($meta as $k => $v) {
                    $sheet->setCellValue('G' . $r, $k);
                    $sheet->setCellValue('H' . $r, $v);
                    $r++;
                }

                // Title + subtitle.
                $sheet->mergeCells('A1:D1');
                $sheet->mergeCells('A2:D2');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('1E3A8A');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('2563EB');
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Info rows.
                $sheet->getStyle('A3:A4')->getFont()->setBold(true);
                $sheet->getStyle('D3:D4')->getFont()->setBold(true);

                // Header row 6.
                $sheet->getStyle('A6:D6')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A6:D6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
                $sheet->getStyle('A6:D6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Borders + alignment on the data table.
                if ($this->lastRow >= $this->dataStartRow) {
                    $range = 'A6:D' . $this->lastRow;
                    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
                    $sheet->getStyle('A6:A' . $this->lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('D6:D' . $this->lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    // Light zebra on data rows.
                    for ($i = $this->dataStartRow; $i <= $this->lastRow; $i++) {
                        if ($i % 2 === 1) {
                            $sheet->getStyle("A{$i}:D{$i}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                        }
                    }
                }

                // Widths + hide Student ID and meta columns + freeze header.
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setVisible(false);
                $sheet->getColumnDimension('C')->setWidth(34);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('G')->setVisible(false);
                $sheet->getColumnDimension('H')->setVisible(false);
                $sheet->freezePane('A7');
            },
        ];
    }
}
