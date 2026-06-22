<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Domain\AI\Services\LlmClient;
use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\Students\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Principal VACUUM — natural-language Q&A over a bounded, school-wide snapshot.
 *
 * The principal is entitled to see whole-school data, so the snapshot is school
 * aggregates (never another school — tenancy already isolates that at the DB).
 * The LLM only ever sees this pre-computed snapshot, never raw table access.
 */
class PrincipalVacuum
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly BalanceQuery $balance,
    ) {}

    public function answer(string $question): array
    {
        $snapshot = $this->snapshot();
        $school = \App\Domain\School\Models\SchoolSetting::schoolName();
        $language = \App\Domain\School\Models\SchoolSetting::languageName();

        $system = <<<PROMPT
You are VACUUM, the AI co-pilot for the principal of "{$school}", a school in Cameroon.
Answer the principal's question using ONLY the SCHOOL SNAPSHOT below.

RULES:
- Reply entirely in {$language}.
- Use only the numbers in the SNAPSHOT. Never invent figures, names, or trends not present.
- If the snapshot doesn't contain the answer, say so plainly and suggest what report would.
- Money is in XAF (CFA). A positive fees balance means the student OWES the school.
- Be concise and decision-useful: lead with the answer, then a short supporting detail or list.
- This is a read-only briefing. You cannot make changes; if asked to, explain that changes are
  done through the admin panel or a confirmed VACUUM command.

SCHOOL SNAPSHOT:
{$snapshot}
PROMPT;

        $answer = $this->llm->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ], temperature: 0.2);

        return ['answer' => $answer, 'records' => []];
    }

    private function snapshot(): string
    {
        $lines = [];

        $activeStudents = Student::where('active', true)->count();
        $lines[] = "Active students: {$activeStudents}";

        // Enrollment by class
        $byClass = DB::table('students')
            ->join('school_classes', 'students.current_class_id', '=', 'school_classes.id')
            ->where('students.active', true)
            ->select('school_classes.name', DB::raw('count(*) as n'))
            ->groupBy('school_classes.name')
            ->orderBy('school_classes.name')
            ->get();
        if ($byClass->isNotEmpty()) {
            $lines[] = 'Enrollment by class: ' . $byClass->map(fn ($r) => "{$r->name}={$r->n}")->implode(', ');
        }

        // Fees: outstanding totals + top debtors
        $balances = Student::where('active', true)->get()
            ->map(fn ($s) => [
                'name' => trim($s->given_name . ' ' . $s->family_name),
                'balance' => $this->balance->get($s->id)['balance'],
            ])
            ->filter(fn ($b) => $b['balance'] > 0)
            ->sortByDesc('balance')
            ->values();
        $totalOwed = $balances->sum('balance');
        $lines[] = "Fees outstanding: {$totalOwed} XAF total, owed by {$balances->count()} students";
        if ($balances->isNotEmpty()) {
            $top = $balances->take(5)->map(fn ($b) => "{$b['name']} ({$b['balance']} XAF)")->implode(', ');
            $lines[] = "Top debtors: {$top}";
        }

        // Results: latest term performance
        $latestTerm = DB::table('term_results')
            ->join('terms', 'term_results.term_id', '=', 'terms.id')
            ->orderByDesc('term_results.created_at')
            ->select('terms.id', 'terms.name')
            ->first();

        if ($latestTerm) {
            $lines[] = "Latest results term: {$latestTerm->name}";

            $results = DB::table('term_results')
                ->join('students', 'term_results.student_id', '=', 'students.id')
                ->leftJoin('streams', 'term_results.stream_id', '=', 'streams.id')
                ->where('term_results.term_id', $latestTerm->id)
                ->select(
                    DB::raw("trim(students.given_name || ' ' || students.family_name) as name"),
                    'streams.name as stream',
                    'term_results.overall_average'
                )
                ->get();

            if ($results->isNotEmpty()) {
                $top = $results->sortByDesc('overall_average')->take(5)
                    ->map(fn ($r) => "{$r->name} ({$r->stream}): {$r->overall_average}/20")->implode(', ');
                $bottom = $results->sortBy('overall_average')->take(5)
                    ->map(fn ($r) => "{$r->name} ({$r->stream}): {$r->overall_average}/20")->implode(', ');
                $failing = $results->where('overall_average', '<', 10)->count();

                $lines[] = "Top performers: {$top}";
                $lines[] = "Lowest performers: {$bottom}";
                $lines[] = "Students below 10/20 average: {$failing} of {$results->count()} ranked";
            }
        } else {
            $lines[] = 'Results: no term results computed yet.';
        }

        return implode("\n", $lines);
    }
}
