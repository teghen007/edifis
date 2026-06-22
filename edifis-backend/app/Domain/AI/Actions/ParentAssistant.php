<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Domain\AI\Services\LlmClient;
use App\Domain\Academics\Models\Mark;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Ledger\Queries\BalanceQuery;
use App\Models\User;

/**
 * Parent-facing AI. Gathers ONLY the asking parent's own children's data
 * (enforced in code, never trusting the model) and answers grounded strictly
 * in that context. The model never receives data about other families or schools.
 */
class ParentAssistant
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly BalanceQuery $balance,
    ) {}

    public function ask(User $parent, string $question): array
    {
        $children = $parent->children()->where('active', true)->get();

        if ($children->isEmpty()) {
            return ['answer' => 'There are no children linked to your account yet. Please contact the school office.'];
        }

        $school = config('app.name');
        $context = $this->buildContext($children, $school);

        $system = <<<PROMPT
You are the EDIFIS parent assistant for "{$school}", a school in Cameroon.
You help this parent understand their own children's school information.

STRICT RULES:
- Use ONLY the data provided in the CONTEXT below. Never invent marks, fees, dates, or names.
- The CONTEXT contains only THIS parent's children. If asked about any other student, another
  family, staff, or another school, politely say you can only help with their own children.
- If the answer is not in the CONTEXT, say you don't have that information and suggest contacting
  the school office. Do not guess.
- Money is in XAF (CFA francs). A positive balance means money is OWED to the school.
- Be warm, concise, and clear. Plain language a parent understands. 2-5 sentences unless a list is needed.

CONTEXT:
{$context}
PROMPT;

        $answer = $this->llm->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ]);

        return ['answer' => $answer];
    }

    private function buildContext(\Illuminate\Support\Collection $children, string $school): string
    {
        $lines = ["School: {$school}", ''];

        foreach ($children as $child) {
            $name = trim(($child->given_name ?? '') . ' ' . ($child->family_name ?? ''));
            $class = $child->schoolClass?->name ?? 'Unknown class';

            $balance = $this->balance->get($child->id)['balance'];

            $marks = Mark::where('student_id', $child->id)->where('published', true)->get();
            $totalMax = (float) $marks->sum('max_score');
            $avg = $totalMax > 0 ? round(($marks->sum('score') / $totalMax) * 20, 2) : null;

            $present = AttendanceEvent::where('student_id', $child->id)->where('status', 'present')->count();

            $lines[] = "Child: {$name}";
            $lines[] = "  Class: {$class}";
            $lines[] = "  Fees balance owed: {$balance} XAF";
            $lines[] = '  Overall average: ' . ($avg !== null ? "{$avg} / 20" : 'not published yet');
            $lines[] = "  Days marked present: {$present}";

            if ($marks->isNotEmpty()) {
                $subjectLines = $marks->take(20)->map(function ($m) {
                    $subj = $m->subject_id ?? 'Subject';
                    return "    - {$subj}: {$m->score}/{$m->max_score}";
                })->implode("\n");
                $lines[] = "  Recent marks:\n{$subjectLines}";
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
