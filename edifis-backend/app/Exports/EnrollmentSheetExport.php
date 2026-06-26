<?php

declare(strict_types=1);

namespace App\Exports;

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
 * Branded subject-enrolment matrix for one stream.
 *
 *   Row 1  Title (school name)
 *   Row 2  SUBJECT ENROLMENT — <class>
 *   Row 3  meta: A="meta_stream_id", B=<stream_id>      (hidden)
 *   Row 4  Header: S/N | Student ID | Student Name | <subject names...>
 *   Row 5  Machine header (hidden): blanks | <subject_id per subject column>
 *   Row 6+ Data: n | id | name | "X" where enrolled
 *
 * The importer locates the stream id, header row and subject columns by content.
 */
class EnrollmentSheetExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private int $lastRow = 5;
    private int $lastCol = 3;

    public function __construct(private readonly string $streamId) {}

    public function title(): string
    {
        return 'Enrollment';
    }

    public function array(): array
    {
        $stream = DB::table('streams')->where('id', $this->streamId)->first();

        $subjects = DB::table('subject_stream')
            ->join('subjects', 'subject_stream.subject_id', '=', 'subjects.id')
            ->where('subject_stream.stream_id', $this->streamId)
            ->select('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->get();

        $students = DB::table('student_stream')
            ->join('students', 'student_stream.student_id', '=', 'students.id')
            ->where('student_stream.stream_id', $this->streamId)
            ->select('students.id', DB::raw("trim(students.given_name || ' ' || students.family_name) as name"))
            ->orderBy('name')
            ->get();

        $enrolled = DB::table('student_subject')
            ->where('stream_id', $this->streamId)
            ->get()
            ->groupBy('student_id')
            ->map(fn ($g) => $g->pluck('subject_id')->all());

        $rows = [];
        $rows[] = [SchoolSetting::schoolName()];                                  // 1
        $rows[] = ['SUBJECT ENROLMENT' . ($stream?->name ? ' — ' . $stream->name : '')]; // 2
        $rows[] = ['meta_stream_id', $this->streamId];                           // 3 (hidden)

        $human = ['S/N', 'Student ID', 'Student Name'];
        $machine = ['', '', ''];
        foreach ($subjects as $s) {
            $human[] = $s->name;
            $machine[] = $s->id;
        }
        $rows[] = $human;       // 4
        $rows[] = $machine;     // 5 (hidden)

        $n = 1;
        foreach ($students as $st) {
            $taken = $enrolled[$st->id] ?? [];
            $row = [$n++, $st->id, $st->name];
            foreach ($subjects as $s) {
                $row[] = in_array($s->id, $taken, true) ? 'X' : '';
            }
            $rows[] = $row;
        }

        $this->lastRow = 5 + $students->count();
        $this->lastCol = 3 + $subjects->count();

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max($this->lastCol, 4));

                $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->mergeCells("A2:{$lastColLetter}2");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('1E3A8A');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('2563EB');
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header row 4.
                $sheet->getStyle("A4:{$lastColLetter}4")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A4:{$lastColLetter}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
                $sheet->getStyle("A4:{$lastColLetter}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setTextRotation(0)->setWrapText(true);

                if ($this->lastRow >= 6) {
                    $range = "A4:{$lastColLetter}" . $this->lastRow;
                    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
                    // Centre the S/N + the subject X cells.
                    $sheet->getStyle('A4:A' . $this->lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $firstSubjCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4);
                    $sheet->getStyle("{$firstSubjCol}6:{$lastColLetter}" . $this->lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getRowDimension(5)->setVisible(false);   // machine header
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setVisible(false);   // Student ID
                $sheet->getColumnDimension('C')->setWidth(30);
                $sheet->getStyle('A3:B3')->getFont()->getColor()->setRGB('FFFFFF'); // hide meta visually
                $sheet->setCellValue("{$lastColLetter}3", 'Mark "X" for each subject a student takes.');
                $sheet->getStyle("{$lastColLetter}3")->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
                $sheet->freezePane('D6');
            },
        ];
    }
}
