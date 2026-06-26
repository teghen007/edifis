<?php

declare(strict_types=1);

namespace App\Imports;

use App\Domain\Academics\Actions\RecordMark;
use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Test;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Reads the branded mark sheet produced by {@see \App\Exports\MarkSheetExport}.
 * Self-describing: meta lives in columns G/H, and the data header row + columns
 * are located by content (so styling/row changes don't break parsing).
 *
 * Columns: 0=S/N, 1=Student ID, 2=Student Name, 3=Marks.
 */
class MarkSheetImport implements \Maatwebsite\Excel\Concerns\ToCollection
{
    private string $streamId = '';
    private string $subjectId = '';
    private string $testId = '';
    private int $maxScore = 20;
    private string $testName = '';
    private string $classId = '';
    private ?int $headerRow = null;

    public function collection(Collection $rows): void
    {
        $meta = [];
        $scan = min(10, $rows->count());
        for ($i = 0; $i < $scan; $i++) {
            $label = trim((string) ($rows[$i][6] ?? ''));   // column G
            $value = trim((string) ($rows[$i][7] ?? ''));   // column H
            if (str_starts_with($label, 'meta_') && $value !== '') {
                $meta[$label] = $value;
            }
        }

        $this->streamId = $meta['meta_stream_id'] ?? '';
        $this->subjectId = $meta['meta_subject_id'] ?? '';
        $this->testId = $meta['meta_test_id'] ?? '';
        $this->maxScore = (int) ($meta['meta_max'] ?? 20);

        if (!$this->streamId || !$this->subjectId || !$this->testId) {
            throw new \RuntimeException('This file is missing its mark-sheet data. Please download a fresh sheet.');
        }

        $test = Test::find($this->testId);
        $this->testName = $test?->name ?? 'Unknown Sequence';
        $stream = Stream::find($this->streamId);
        $this->classId = $stream?->class_id ?? '';

        if (!$this->classId) {
            throw new \RuntimeException('Stream not found for class_id.');
        }

        $this->headerRow = $this->findHeaderRow($rows);
    }

    private function findHeaderRow(Collection $rows): int
    {
        for ($i = 0; $i < $rows->count(); $i++) {
            foreach ($rows[$i] as $cell) {
                if (trim((string) $cell) === 'Student ID') {
                    return $i;
                }
            }
        }

        return 5; // sensible default (header on row 6)
    }

    public function ingest(Collection $rows, string $ownerTeacherId): array
    {
        $recordMark = app(RecordMark::class);
        $saved = 0;
        $skipped = [];
        $errors = [];

        $start = ($this->headerRow ?? 5) + 1;

        for ($i = $start; $i < $rows->count(); $i++) {
            $row = $rows[$i];
            $studentId = trim((string) ($row[1] ?? ''));   // Student ID
            $marksStr = trim((string) ($row[3] ?? ''));    // Marks

            if ($studentId === '') {
                continue;
            }

            if ($marksStr === '') {
                $skipped[] = $i + 1;
                continue;
            }

            if (!is_numeric($marksStr)) {
                $errors[] = ['row' => $i + 1, 'reason' => "Non-numeric marks value: {$marksStr}"];
                continue;
            }

            $marks = (float) $marksStr;
            if ($marks < 0 || $marks > $this->maxScore) {
                $errors[] = ['row' => $i + 1, 'reason' => "Marks {$marks} outside range 0-{$this->maxScore}"];
                continue;
            }

            $enrolled = DB::table('student_stream')->where('student_id', $studentId)->where('stream_id', $this->streamId)->exists()
                && DB::table('student_subject')->where('student_id', $studentId)->where('subject_id', $this->subjectId)->exists();

            if (!$enrolled) {
                $errors[] = ['row' => $i + 1, 'reason' => 'Student not enrolled in this stream/subject'];
                continue;
            }

            try {
                $recordMark->handle(
                    id: (string) Uuid::uuid7(),
                    revision: (string) Uuid::uuid7(),
                    revisionParent: null,
                    studentId: $studentId,
                    subjectId: $this->subjectId,
                    classId: $this->classId,
                    sequence: $this->testName,
                    ownerTeacherId: $ownerTeacherId,
                    score: $marks,
                    maxScore: (float) $this->maxScore,
                    published: true,
                );
                $saved++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $i + 1, 'reason' => $e->getMessage()];
            }
        }

        return compact('saved', 'skipped', 'errors');
    }

    public function getStreamId(): string
    {
        return $this->streamId;
    }

    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    public function getTestId(): string
    {
        return $this->testId;
    }
}
