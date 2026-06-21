<?php

declare(strict_types=1);

namespace App\Imports;

use App\Domain\Academics\Actions\RecordMark;
use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Test;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Ramsey\Uuid\Uuid;

class MarkSheetImport implements ToCollection
{
    private string $streamId;
    private string $subjectId;
    private string $testId;
    private int $maxScore;
    private string $testName;
    private string $classId;

    public function collection(Collection $rows)
    {
        $metaMap = [];
        for ($i = 0; $i < min(4, $rows->count()); $i++) {
            $label = (string) ($rows[$i][3] ?? '');
            $value = (string) ($rows[$i][4] ?? '');
            if ($label && $value) {
                $metaMap[$label] = $value;
            }
        }

        $this->streamId = $metaMap['meta_stream_id'] ?? '';
        $this->subjectId = $metaMap['meta_subject_id'] ?? '';
        $this->testId = $metaMap['meta_test_id'] ?? '';
        $this->maxScore = (int) ($metaMap['meta_max'] ?? 20);

        if (!$this->streamId || !$this->subjectId || !$this->testId) {
            throw new \RuntimeException('Missing meta data in uploaded file.');
        }

        $test = Test::find($this->testId);
        $this->testName = $test?->name ?? 'Unknown Sequence';
        $stream = Stream::with('schoolClass')->find($this->streamId);
        $this->classId = $stream?->class_id ?? '';

        if (!$this->classId) {
            throw new \RuntimeException('Stream not found for class_id.');
        }
    }

    public function process(string $ownerTeacherId): array
    {
        $recordMark = app(RecordMark::class);
        $saved = 0;
        $skipped = [];
        $errors = [];

        return compact('saved', 'skipped', 'errors');
    }

    public function ingest(Collection $rows, string $ownerTeacherId): array
    {
        $recordMark = app(RecordMark::class);
        $saved = 0;
        $skipped = [];
        $errors = [];

        for ($i = 0; $i < $rows->count(); $i++) {
            $row = $rows[$i];
            $studentId = (string) ($row[0] ?? '');
            $marksStr = (string) ($row[2] ?? '');

            if (empty($studentId) || $i < 6) {
                continue;
            }

            if ($marksStr === '' || $marksStr === null) {
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
                $errors[] = ['row' => $i + 1, 'reason' => "Student not enrolled in this stream/subject"];
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

    public function getStreamId(): string { return $this->streamId; }
    public function getSubjectId(): string { return $this->subjectId; }
    public function getTestId(): string { return $this->testId; }
}
