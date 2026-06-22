<?php

declare(strict_types=1);

namespace App\Domain\AI\Jobs;

use App\Domain\AI\Services\LlmClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * After results are computed, write a short AI remark on each student's term
 * report card. Runs in the background (Horizon) so compute stays fast.
 * Grounded strictly in the student's own computed subject results.
 */
class GenerateTermRemarks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        private readonly string $streamId,
        private readonly string $termId,
    ) {}

    public function handle(LlmClient $llm): void
    {
        if (!$llm->configured()) {
            return;
        }

        $school = \App\Domain\School\Models\SchoolSetting::schoolName();

        $termResults = DB::table('term_results')
            ->where('stream_id', $this->streamId)
            ->where('term_id', $this->termId)
            ->get();

        foreach ($termResults as $tr) {
            try {
                $student = DB::table('students')->where('id', $tr->student_id)->first();
                if (!$student) {
                    continue;
                }
                $name = trim(($student->given_name ?? '') . ' ' . ($student->family_name ?? ''));

                $subjects = DB::table('subject_results')
                    ->join('subjects', 'subject_results.subject_id', '=', 'subjects.id')
                    ->where('subject_results.student_id', $tr->student_id)
                    ->where('subject_results.term_id', $this->termId)
                    ->select('subjects.name', 'subject_results.average', 'subject_results.grade')
                    ->orderByDesc('subject_results.average')
                    ->get();

                $subjectList = $subjects
                    ->map(fn ($s) => "{$s->name}: {$s->average}/20 ({$s->grade})")
                    ->implode(', ');

                $system = <<<PROMPT
You write brief, professional report-card remarks for a secondary school in Cameroon ("{$school}").
Write ONE encouraging, specific remark (1-2 sentences, max 40 words) for the named student, based ONLY
on the data given. Name the strongest area and the area to improve. No invented facts, no scores the
data doesn't show. Address the student in the third person (e.g. "Goodness shows...").
PROMPT;

                $user = "Student: {$name}\n"
                    . "Overall: {$tr->overall_average}/20, grade {$tr->grade}, position {$tr->position}\n"
                    . "Subjects (best to worst): {$subjectList}";

                $remark = $llm->chat([
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ], temperature: 0.6, maxTokens: 120);

                if ($remark !== '') {
                    DB::table('term_results')
                        ->where('id', $tr->id)
                        ->update(['ai_remark' => $remark, 'updated_at' => now()]);
                }
            } catch (\Throwable $e) {
                Log::warning('Remark generation failed for a student', [
                    'student_id' => $tr->student_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
