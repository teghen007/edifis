<?php

declare(strict_types=1);

namespace App\Domain\Academics\Services;

use App\Domain\Academics\Models\AcademicYear;
use App\Domain\Academics\Models\Term;
use App\Domain\Promotion\Actions\DeliberateStream;
use App\Domain\Results\Actions\ComputeResults;
use Illuminate\Support\Facades\DB;

/**
 * Academic season rotation.
 *
 * The school moves through Term 1 -> 2 -> 3 -> year-end like a sports season.
 * Exactly one term is "active" (the current pointer). Within a term there are
 * two sequences (Cameroon norm). The model is SOFT: closing a term computes its
 * results but an admin can reopen it for late marks; year-end runs the promotion
 * deliberation and opens the next year.
 */
class SeasonService
{
    public function __construct(
        private readonly ComputeResults $computeResults,
        private readonly DeliberateStream $deliberateStream,
    ) {
    }

    /** Snapshot of where the school currently is. */
    public function current(): array
    {
        $year = AcademicYear::where('is_current', true)->first();

        if (! $year) {
            return ['has_season' => false];
        }

        $terms = $year->terms()->orderBy('position')->get();
        $active = $terms->where('status', Term::STATUS_ACTIVE)->sortByDesc('position')->first();
        $allClosed = $terms->isNotEmpty()
            && $terms->every(fn ($t) => $t->status === Term::STATUS_CLOSED);

        return [
            'has_season' => true,
            'year' => [
                'id' => $year->id,
                'name' => $year->name,
                'starts_on' => optional($year->starts_on)->toDateString(),
                'ends_on' => optional($year->ends_on)->toDateString(),
            ],
            'current_term' => $active ? $this->termPayload($active) : null,
            'current_sequence' => $active?->current_sequence,
            'global_sequence' => $active?->globalSequence(),
            'can_open_next_sequence' => $active && $active->current_sequence < Term::SEQUENCES_PER_TERM,
            'can_advance' => (bool) $active,
            'can_close_year' => $allClosed,
            'terms' => $terms->map(fn ($t) => $this->termPayload($t))->values()->all(),
        ];
    }

    /** Open the second sequence of the active term. */
    public function openNextSequence(): array
    {
        $active = $this->activeTermOrFail();

        abort_if(
            $active->current_sequence >= Term::SEQUENCES_PER_TERM,
            422,
            'Both sequences of this term are already open.'
        );

        $active->update(['current_sequence' => $active->current_sequence + 1]);

        return $this->current();
    }

    /** Close the active term (computing its results) and open the next one. */
    public function advanceTerm(): array
    {
        return DB::transaction(function () {
            $year = $this->currentYearOrFail(true);
            $active = $year->terms()->active()->orderByDesc('position')->lockForUpdate()->first();
            abort_unless($active, 422, 'There is no active term to advance.');

            $compute = $this->computeTermForAllStreams($year->id, $active->id);

            $active->update([
                'status' => Term::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            $next = $year->terms()->where('position', $active->position + 1)->first();
            if ($next) {
                $next->update([
                    'status' => Term::STATUS_ACTIVE,
                    'current_sequence' => 1,
                ]);
            }

            return [
                'closed_term' => $active->name,
                'opened_term' => $next?->name,
                'streams_computed' => $compute['ok'],
                'compute_errors' => $compute['errors'],
            ] + $this->current();
        });
    }

    /** Admin re-opens a closed term for late marks/corrections (soft & reversible). */
    public function reopenTerm(string $termId): array
    {
        $term = Term::findOrFail($termId);

        abort_unless(
            $term->status === Term::STATUS_CLOSED,
            422,
            'Only a closed term can be reopened.'
        );

        $term->update(['status' => Term::STATUS_ACTIVE, 'closed_at' => null]);

        return $this->current();
    }

    /** End the year: deliberate promotions, archive it, open the next year. */
    public function closeYear(): array
    {
        return DB::transaction(function () {
            $year = $this->currentYearOrFail(true);
            $terms = $year->terms()->orderBy('position')->get();

            abort_unless(
                $terms->isNotEmpty() && $terms->every(fn ($t) => $t->status === Term::STATUS_CLOSED),
                422,
                'Close all three terms before ending the academic year.'
            );

            $streamIds = DB::table('streams')->where('academic_year_id', $year->id)->pluck('id');
            $deliberated = 0;
            $errors = [];
            foreach ($streamIds as $sid) {
                try {
                    $this->deliberateStream->handle($sid, $year->id);
                    $deliberated++;
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }

            $year->update(['is_current' => false]);

            $newYear = AcademicYear::create([
                'name' => $this->nextYearName($year->name),
                'is_current' => true,
                'starts_on' => optional($year->starts_on)?->addYear(),
                'ends_on' => optional($year->ends_on)?->addYear(),
            ]);

            foreach ([1, 2, 3] as $pos) {
                Term::create([
                    'name' => "Term {$pos}",
                    'academic_year_id' => $newYear->id,
                    'position' => $pos,
                    'status' => $pos === 1 ? Term::STATUS_ACTIVE : Term::STATUS_UPCOMING,
                    'current_sequence' => 1,
                ]);
            }

            return [
                'archived_year' => $year->name,
                'new_year' => $newYear->name,
                'streams_deliberated' => $deliberated,
                'deliberation_errors' => $errors,
            ] + $this->current();
        });
    }

    /** All academic years (for the admin archive selector). */
    public function years(): array
    {
        return AcademicYear::orderByDesc('name')
            ->get()
            ->map(fn ($y) => [
                'id' => $y->id,
                'name' => $y->name,
                'is_current' => (bool) $y->is_current,
            ])
            ->all();
    }

    private function termPayload(Term $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'position' => $t->position,
            'status' => $t->status,
            'current_sequence' => $t->current_sequence,
            'closed_at' => optional($t->closed_at)->toDateTimeString(),
        ];
    }

    private function computeTermForAllStreams(string $yearId, string $termId): array
    {
        $streamIds = DB::table('streams')->where('academic_year_id', $yearId)->pluck('id');
        $ok = 0;
        $errors = [];

        foreach ($streamIds as $sid) {
            try {
                $this->computeResults->handle($sid, $termId);
                $ok++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return ['ok' => $ok, 'errors' => $errors];
    }

    private function currentYearOrFail(bool $lock = false): AcademicYear
    {
        $query = AcademicYear::where('is_current', true);
        if ($lock) {
            $query->lockForUpdate();
        }
        $year = $query->first();
        abort_unless($year, 422, 'No active academic year is set.');

        return $year;
    }

    private function activeTermOrFail(): Term
    {
        $year = $this->currentYearOrFail();
        $active = $year->terms()->active()->orderByDesc('position')->first();
        abort_unless($active, 422, 'There is no active term.');

        return $active;
    }

    private function nextYearName(string $name): string
    {
        if (preg_match('/(\d{4})\D+(\d{4})/', $name, $m)) {
            return ((int) $m[1] + 1) . '-' . ((int) $m[2] + 1);
        }

        if (preg_match('/(\d{4})/', $name, $m)) {
            return (string) ((int) $m[1] + 1);
        }

        return $name . ' (next)';
    }
}
