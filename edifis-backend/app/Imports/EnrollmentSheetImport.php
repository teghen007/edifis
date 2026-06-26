<?php

declare(strict_types=1);

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Reads the matrix produced by {@see \App\Exports\EnrollmentSheetExport} and
 * syncs the student_subject pivot for the stream.
 */
class EnrollmentSheetImport implements ToCollection
{
    private string $streamId = '';

    /** @var array<int,string> column index => subject_id */
    private array $subjectCols = [];

    private array $result = ['added' => 0, 'removed' => 0, 'errors' => []];

    private const TRUTHY = ['x', 'X', '1', 'yes', 'y', 'true', '✓'];

    public function collection(Collection $rows): void
    {
        // Locate by content: stream id (meta_stream_id), the human header row
        // (contains "Student ID"), the machine header (next row, subject ids),
        // and the first data row.
        $headerRow = null;
        for ($i = 0; $i < $rows->count(); $i++) {
            foreach ($rows[$i] as $col => $val) {
                $cell = trim((string) $val);
                if ($cell === 'meta_stream_id') {
                    $this->streamId = trim((string) ($rows[$i][(int) $col + 1] ?? ''));
                }
                if ($cell === 'Student ID') {
                    $headerRow = $i;
                }
            }
            if ($this->streamId !== '' && $headerRow !== null) {
                break;
            }
        }

        // Machine header is the row right after the human header; subject ids in col >= 3.
        $machineIdx = $headerRow !== null ? $headerRow + 1 : 4;
        $machine = $rows[$machineIdx] ?? collect();
        foreach ($machine as $col => $val) {
            if ((int) $col >= 3 && trim((string) $val) !== '') {
                $this->subjectCols[(int) $col] = trim((string) $val);
            }
        }

        if ($this->streamId === '' || empty($this->subjectCols)) {
            $this->result['errors'][] = ['row' => 1, 'reason' => 'Invalid template (missing stream or subject headers).'];
            return;
        }

        $added = 0;
        $removed = 0;

        // Data rows start after the machine header.
        for ($i = $machineIdx + 1; $i < $rows->count(); $i++) {
            $row = $rows[$i];
            $studentId = trim((string) ($row[1] ?? ''));   // Student ID column
            if ($studentId === '') {
                continue;
            }

            $inStream = DB::table('student_stream')
                ->where('student_id', $studentId)
                ->where('stream_id', $this->streamId)
                ->exists();

            if (!$inStream) {
                $this->result['errors'][] = ['row' => $i + 1, 'reason' => 'Student not in this stream.'];
                continue;
            }

            foreach ($this->subjectCols as $col => $subjectId) {
                $cell = trim((string) ($row[$col] ?? ''));
                $take = in_array(strtolower($cell), array_map('strtolower', self::TRUTHY), true);

                $exists = DB::table('student_subject')
                    ->where('student_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->exists();

                if ($take && !$exists) {
                    DB::table('student_subject')->insert([
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                        'stream_id' => $this->streamId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $added++;
                } elseif (!$take && $exists) {
                    DB::table('student_subject')
                        ->where('student_id', $studentId)
                        ->where('subject_id', $subjectId)
                        ->delete();
                    $removed++;
                }
            }
        }

        $this->result['added'] = $added;
        $this->result['removed'] = $removed;
    }

    public function getStreamId(): string
    {
        return $this->streamId;
    }

    public function getResult(): array
    {
        return $this->result;
    }
}
