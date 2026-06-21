<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Subject-enrollment matrix for one stream.
 *
 * Layout (so the import can self-describe regardless of column order):
 *   Row 1  → meta: A="meta_stream_id", B=<stream_id>
 *   Row 2  → human header: "Student ID", "Student Name", then each subject NAME
 *   Row 3  → machine header (HIDDEN): blank, blank, then each subject_id
 *   Row 4+ → data: student_id, name, then "X" if enrolled / blank otherwise
 */
class EnrollmentSheetExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    public function __construct(private readonly string $streamId) {}

    public function title(): string
    {
        return 'Enrollment';
    }

    public function array(): array
    {
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

        // Row 1 — meta
        $meta = ['meta_stream_id', $this->streamId];
        foreach ($subjects as $_) {
            $meta[] = '';
        }
        $rows[] = $meta;

        // Row 2 — human header
        $human = ['Student ID', 'Student Name'];
        foreach ($subjects as $s) {
            $human[] = $s->name;
        }
        $rows[] = $human;

        // Row 3 — machine header (subject ids), hidden on export
        $machine = ['', ''];
        foreach ($subjects as $s) {
            $machine[] = $s->id;
        }
        $rows[] = $machine;

        // Row 4+ — data
        foreach ($students as $st) {
            $taken = $enrolled[$st->id] ?? [];
            $r = [$st->id, $st->name];
            foreach ($subjects as $s) {
                $r[] = in_array($s->id, $taken, true) ? 'X' : '';
            }
            $rows[] = $r;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Hide the machine-header row (subject ids) and the meta row's id value visually.
                $sheet->getRowDimension(3)->setVisible(false);

                // Bold the human header row.
                $highestCol = $sheet->getHighestColumn();
                $sheet->getStyle("A2:{$highestCol}2")->getFont()->setBold(true);

                // Grey out the meta row so users leave it alone.
                $sheet->getStyle('A1:B1')->getFont()->getColor()
                    ->setRGB('999999');
                $sheet->setCellValue('D1', 'Mark "X" for each subject a student takes. Do not edit rows 1-3.');
                $sheet->getStyle('D1')->getFont()->setItalic(true)->getColor()->setRGB('999999');
            },
        ];
    }
}
