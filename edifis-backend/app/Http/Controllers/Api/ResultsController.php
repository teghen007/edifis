<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\AI\Jobs\GenerateTermRemarks;
use App\Domain\Notifications\Channels\FcmChannel;
use App\Domain\Notifications\Notifications\ResultsPublished;
use App\Domain\Results\Actions\ComputeResults;
use App\Domain\Students\Models\Student;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResultsController
{
    public function __construct(private readonly FcmChannel $fcm) {}

    public function compute(Request $request, ComputeResults $compute): JsonResponse
    {
        $validated = $request->validate([
            'stream_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
        ]);

        $result = $compute->handle($validated['stream_id'], $validated['term_id']);

        $this->notifyParents($validated['stream_id'], $validated['term_id']);

        // Generate AI report-card remarks in the background (Horizon).
        GenerateTermRemarks::dispatch($validated['stream_id'], $validated['term_id']);

        return response()->json($result);
    }

    private function notifyParents(string $streamId, string $termId): void
    {
        try {
            $term = DB::table('terms')->where('id', $termId)->first();
            $termName = $term?->name ?? 'Term';

            $rankedStudents = DB::table('term_results')
                ->where('stream_id', $streamId)
                ->where('term_id', $termId)
                ->get();

            foreach ($rankedStudents as $tr) {
                $student = Student::find($tr->student_id);
                if (!$student) {
                    continue;
                }

                $studentName = trim($student->given_name . ' ' . $student->family_name);

                $parentIds = DB::table('guardian_student')
                    ->where('student_id', $tr->student_id)
                    ->pluck('guardian_id');

                foreach ($parentIds as $parentId) {
                    $parent = User::find($parentId);
                    if (!$parent) {
                        continue;
                    }
                    $this->fcm->send($parent, new ResultsPublished(
                        $tr->student_id,
                        $studentName,
                        $termName,
                        (float) $tr->overall_average,
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send results notifications', [
                'stream_id' => $streamId,
                'term_id' => $termId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** School-wide performance aggregates for the principal's charts (latest computed term). */
    public function overview(): JsonResponse
    {
        $latest = DB::table('term_results')->orderByDesc('created_at')->first();
        if (!$latest) {
            return response()->json(['has_data' => false]);
        }

        $termId = $latest->term_id;
        $rows = DB::table('term_results')->where('term_id', $termId)->get();
        $total = $rows->count();
        $passed = $rows->where('overall_average', '>=', 10)->count();

        $top = DB::table('term_results')
            ->join('students', 'term_results.student_id', '=', 'students.id')
            ->leftJoin('streams', 'term_results.stream_id', '=', 'streams.id')
            ->where('term_results.term_id', $termId)
            ->orderByDesc('term_results.overall_average')
            ->limit(5)
            ->selectRaw("trim(students.given_name || ' ' || students.family_name) as name, streams.name as stream, term_results.overall_average as average")
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'stream' => $r->stream ?? '', 'average' => (float) $r->average]);

        $byStream = DB::table('term_results')
            ->leftJoin('streams', 'term_results.stream_id', '=', 'streams.id')
            ->where('term_results.term_id', $termId)
            ->groupBy('streams.name')
            ->selectRaw('streams.name as stream, ROUND(AVG(term_results.overall_average)::numeric, 2) as average')
            ->orderByDesc('average')
            ->get()
            ->map(fn ($r) => ['stream' => $r->stream ?? '—', 'average' => (float) $r->average]);

        $grades = $rows->groupBy('grade')->map->count();
        $gradeDist = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $g) {
            $gradeDist[$g] = (int) ($grades[$g] ?? 0);
        }

        return response()->json([
            'has_data' => true,
            'term_name' => DB::table('terms')->where('id', $termId)->value('name') ?? 'Term',
            'students_ranked' => $total,
            'school_average' => round((float) $rows->avg('overall_average'), 2),
            'pass_rate' => $total > 0 ? (int) round($passed / $total * 100) : 0,
            'passed' => $passed,
            'top_students' => $top->values(),
            'by_stream' => $byStream->values(),
            'grade_distribution' => $gradeDist,
        ]);
    }

    public function reportCard(Request $request): JsonResponse
    {
        $studentId = $request->query('student_id');
        $termId = $request->query('term_id');

        abort_unless($studentId && $termId, 400, 'Missing student_id or term_id');

        $this->authorizeStudentAccess($request, $studentId);

        $data = $this->buildReportCard($studentId, $termId);

        if ($data === null) {
            return response()->json(['message' => 'No results found for this student/term.'], 404);
        }

        return response()->json($data);
    }

    public function reportCardPdf(Request $request): Response
    {
        $studentId = $request->query('student_id');
        $termId = $request->query('term_id');

        abort_unless($studentId && $termId, 400, 'Missing student_id or term_id');

        $this->authorizeStudentAccess($request, $studentId);

        $data = $this->buildReportCard($studentId, $termId);
        abort_if($data === null, 404, 'No results found for this student/term.');

        $data['school_name'] = \App\Domain\School\Models\SchoolSetting::schoolName();
        $data['generated_at'] = now()->format('d M Y, H:i');

        $pdf = Pdf::loadView('results.report-card', $data)->setPaper('a4');

        $fileName = 'report-card-' . str_replace(' ', '-', strtolower($data['student_name'])) . '.pdf';

        return $pdf->download($fileName);
    }

    private function authorizeStudentAccess(Request $request, string $studentId): void
    {
        $user = $request->user();
        if ($user->hasRole('parent')) {
            abort_unless($user->ownsStudent($studentId), 403);
        }
    }

    private function buildReportCard(string $studentId, string $termId): ?array
    {
        $termResult = DB::table('term_results')
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->first();

        if (!$termResult) {
            return null;
        }

        $student = DB::table('students')->where('id', $studentId)->first();
        $stream = DB::table('streams')->where('id', $termResult->stream_id)->first();
        $term = DB::table('terms')->where('id', $termId)->first();

        $outOf = DB::table('term_results')
            ->where('stream_id', $termResult->stream_id)
            ->where('term_id', $termId)
            ->count();

        $subjects = DB::table('subject_results')
            ->join('subjects', 'subject_results.subject_id', '=', 'subjects.id')
            ->leftJoin('grade_rules', 'subject_results.grade', '=', 'grade_rules.grade')
            ->where('subject_results.student_id', $studentId)
            ->where('subject_results.term_id', $termId)
            ->select(
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subject_results.average',
                'subject_results.grade',
                'grade_rules.remark',
                DB::raw('COALESCE(subjects.coefficient, 1) as coefficient'),
                DB::raw('ROUND((subject_results.average * COALESCE(subjects.coefficient, 1))::numeric, 2) as weighted')
            )
            ->orderBy('subjects.name')
            ->get();

        $classStatsMap = DB::table('subject_results')
            ->where('stream_id', $termResult->stream_id)
            ->where('term_id', $termId)
            ->select(
                'subject_id',
                DB::raw('ROUND(AVG(average)::numeric, 2) as class_avg'),
                DB::raw('MAX(average) as class_high'),
                DB::raw('MIN(average) as class_low')
            )
            ->groupBy('subject_id')
            ->get()
            ->keyBy('subject_id');

        $subjects = $subjects->map(function ($s) use ($classStatsMap) {
            $stats = $classStatsMap->get($s->subject_id);
            $s->class_avg = $stats->class_avg ?? null;
            $s->class_high = $stats->class_high ?? null;
            $s->class_low = $stats->class_low ?? null;
            return $s;
        });

        $lang = \App\Domain\School\Models\SchoolSetting::language();
        $overallAvg = (float) $termResult->overall_average;
        $mentions = $lang === 'fr'
            ? [18 => 'Excellent', 16 => 'Très Bien', 14 => 'Bien', 12 => 'Assez Bien', 10 => 'Passable', 0 => 'Faible']
            : [18 => 'Excellent', 16 => 'Very Good', 14 => 'Good', 12 => 'Fairly Good', 10 => 'Average', 0 => 'Weak'];
        $mention = 'Weak';
        foreach ($mentions as $floor => $label) {
            if ($overallAvg >= $floor) { $mention = $label; break; }
        }

        $classAverage = DB::table('term_results')
            ->where('stream_id', $termResult->stream_id)
            ->where('term_id', $termId)
            ->select(DB::raw('ROUND(AVG(overall_average)::numeric, 2) as class_average'))
            ->value('class_average');

        $conduct = DB::table('conduct_records')
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->first();

        return [
            'student_name' => trim(($student->given_name ?? '') . ' ' . ($student->family_name ?? '')),
            'stream_name' => $stream->name ?? '',
            'term_name' => $term->name ?? '',
            'overall_average' => $termResult->overall_average,
            'grade' => $termResult->grade,
            'mention' => $mention,
            'class_average' => $classAverage,
            'position' => $termResult->position,
            'out_of' => $outOf,
            'ai_remark' => $termResult->ai_remark ?? null,
            'conduct_grade' => $conduct->conduct_grade ?? null,
            'conduct_comment' => $conduct->comment ?? null,
            'language' => $lang,
            'subjects' => $subjects,
        ];
    }

    public function mastersheet(Request $request): JsonResponse
    {
        $streamId = $request->query('stream_id');
        $termId = $request->query('term_id');

        abort_unless($streamId && $termId, 400, 'Missing stream_id or term_id');

        $stream = DB::table('streams')->where('id', $streamId)->first();
        $term = DB::table('terms')->where('id', $termId)->first();

        $subjectList = DB::table('subject_results')
            ->join('subjects', 'subject_results.subject_id', '=', 'subjects.id')
            ->where('subject_results.stream_id', $streamId)
            ->where('subject_results.term_id', $termId)
            ->select('subjects.id', 'subjects.name')
            ->distinct()
            ->orderBy('subjects.name')
            ->get();

        $students = DB::table('term_results')
            ->join('students', 'term_results.student_id', '=', 'students.id')
            ->where('term_results.stream_id', $streamId)
            ->where('term_results.term_id', $termId)
            ->select('students.id as student_id', 'students.given_name', 'students.family_name', 'term_results.*')
            ->orderBy('term_results.position')
            ->get()
            ->map(function ($s) use ($termId) {
                $subjectAvgs = DB::table('subject_results')
                    ->join('subjects', 'subject_results.subject_id', '=', 'subjects.id')
                    ->where('subject_results.student_id', $s->student_id)
                    ->where('subject_results.term_id', $termId)
                    ->pluck('subject_results.average', 'subjects.name');

                return [
                    'name' => trim($s->given_name . ' ' . $s->family_name),
                    'marks' => $subjectAvgs,
                    'overall_average' => $s->overall_average,
                    'grade' => $s->grade,
                    'position' => $s->position,
                ];
            });

        return response()->json([
            'stream_name' => $stream->name ?? '',
            'term_name' => $term->name ?? '',
            'subjects' => $subjectList->pluck('name'),
            'students' => $students,
        ]);
    }
}
