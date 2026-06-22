<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\Test;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;

class MarkSheetExport implements FromCollection, WithEvents, WithStrictNullComparison
{
    public function __construct(
        private readonly string $streamId,
        private readonly string $subjectId,
        private readonly string $testId,
    ) {}

    public function collection(): Collection
    {
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

        return $students->map(fn ($s) => [
            'student_id' => $s->student_id,
            'Student Name' => $s->student_name,
            'Marks' => '',
        ]);
    }

    public function registerEvents(): array
    {
        $stream = Stream::with('schoolClass')->find($this->streamId);
        $subject = Subject::find($this->subjectId);
        $test = Test::with('term')->find($this->testId);

        $meta = [
            ['meta_stream_id', $this->streamId],
            ['meta_subject_id', $this->subjectId],
            ['meta_test_id', $this->testId],
            ['meta_max', $test?->default_max ?? 20],
        ];

        return [
            AfterSheet::class => function (AfterSheet $event) use ($stream, $subject, $test, $meta) {
                $sheet = $event->sheet->getDelegate();

                $sheet->setCellValue('A1', 'School:');
                $sheet->setCellValue('B1', \App\Domain\School\Models\SchoolSetting::schoolName());
                $sheet->setCellValue('A2', 'Stream:');
                $sheet->setCellValue('B2', ($stream?->name ?? '') . ' (' . ($stream?->schoolClass?->name ?? '') . ')');
                $sheet->setCellValue('A3', 'Subject:');
                $sheet->setCellValue('B3', $subject?->name ?? '');
                $sheet->setCellValue('A4', 'Test:');
                $sheet->setCellValue('B4', ($test?->name ?? '') . ' (' . ($test?->term?->name ?? '') . ')');
                $sheet->setCellValue('A5', 'Max Score:');
                $sheet->setCellValue('B5', $test?->default_max ?? 20);

                foreach ($meta as $i => $row) {
                    $sheet->setCellValue('D' . ($i + 1), $row[0]);
                    $sheet->setCellValue('E' . ($i + 1), $row[1]);
                }

                $sheet->getStyle('A1:B5')->getFont()->setBold(true);
                $sheet->getStyle('D1:E4')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
            },
        ];
    }
}
