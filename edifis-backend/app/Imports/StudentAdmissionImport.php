<?php

declare(strict_types=1);

namespace App\Imports;

use App\Domain\Academics\Models\Stream;
use App\Domain\Students\Actions\EnrolStudent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Reads the {@see \App\Exports\StudentAdmissionTemplate} sheet and enrols each
 * student via {@see EnrolStudent} — which places them in the section and
 * creates/reuses the phone-keyed guardian account (siblings dedup automatically).
 */
class StudentAdmissionImport implements ToCollection
{
    private array $result = ['created' => 0, 'skipped' => 0, 'errors' => []];

    public function collection(Collection $rows): void
    {
        // Locate the header row by content ("Given name").
        $headerRow = null;
        $cols = [];
        for ($i = 0; $i < $rows->count(); $i++) {
            foreach ($rows[$i] as $c => $v) {
                if ($this->norm($v) === 'given name') {
                    $headerRow = $i;
                }
            }
            if ($headerRow !== null) {
                break;
            }
        }

        if ($headerRow === null) {
            $this->result['errors'][] = 'Could not find the header row (a "Given name" column).';

            return;
        }

        foreach ($rows[$headerRow] as $c => $v) {
            $cols[$this->norm($v)] = $c;
        }

        $streamByName = Stream::all()->keyBy(fn ($s) => strtolower(trim($s->name)));
        $enrol = app(EnrolStudent::class);

        for ($i = $headerRow + 1; $i < $rows->count(); $i++) {
            $row = $rows[$i];
            $given = $this->cell($row, $cols, 'given name');
            $family = $this->cell($row, $cols, 'family name');

            if ($given === '' && $family === '') {
                continue; // blank row
            }

            try {
                $sectionName = strtolower(trim($this->cell($row, $cols, 'section')));
                $stream = $streamByName[$sectionName] ?? null;
                if (! $stream) {
                    throw new \RuntimeException("Unknown section \"{$this->cell($row, $cols, 'section')}\".");
                }

                $boarding = str_contains(strtolower($this->cell($row, $cols, 'boarding (day/boarding)')), 'board')
                    ? 'boarding' : 'day';

                $enrol->handle([
                    'student' => [
                        'given_name' => $given,
                        'family_name' => $family,
                        'other_names' => $this->cell($row, $cols, 'other names') ?: null,
                        'sex' => $this->sex($this->cell($row, $cols, 'sex (m/f)')),
                        'date_of_birth' => $this->date($this->cell($row, $cols, 'date of birth (yyyy-mm-dd)')),
                        'stream_id' => $stream->id,
                        'boarding_status' => $boarding,
                    ],
                    'consent' => [
                        'consenter_name' => $this->cell($row, $cols, 'guardian name') ?: 'Guardian',
                        'relationship' => $this->cell($row, $cols, 'relationship') ?: 'guardian',
                        'consenter_contact' => $this->cell($row, $cols, 'guardian phone') ?: null,
                        'scope' => ['academic_records', 'parent_portal'],
                    ],
                ]);

                $this->result['created']++;
            } catch (\Throwable $e) {
                $this->result['skipped']++;
                $this->result['errors'][] = 'Row ' . ($i + 1) . ': ' . $e->getMessage();
            }
        }
    }

    public function getResult(): array
    {
        return $this->result;
    }

    private function cell(Collection|array $row, array $cols, string $key): string
    {
        $idx = $cols[$key] ?? null;

        return $idx === null ? '' : trim((string) ($row[$idx] ?? ''));
    }

    private function norm($v): string
    {
        return strtolower(trim((string) $v));
    }

    private function sex(string $v): ?string
    {
        $v = strtoupper(substr(trim($v), 0, 1));

        return in_array($v, ['M', 'F'], true) ? $v : null;
    }

    private function date(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
