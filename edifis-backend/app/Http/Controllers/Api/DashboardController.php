<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Academics\Models\Mark;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Students\Models\Student;
use App\Domain\Timetable\Models\TimetableEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->getRoleNames()->first() ?? 'unknown';

        $cards = $this->cardsForRole($role, $user);

        return response()->json(['cards' => $cards]);
    }

    private function cardsForRole(string $role, $user): array
    {
        return match ($role) {
            'principal', 'vice_principal' => $this->cardsForPrincipal(),
            'bursar'                      => $this->cardsForBursar(),
            'class_master'                => $this->cardsForClassMaster($user),
            'subject_teacher'             => $this->cardsForSubjectTeacher($user),
            'secretary'                   => $this->cardsForSecretary(),
            'discipline_master'           => $this->cardsForDisciplineMaster(),
            default                       => $this->cardsForDefault(),
        };
    }

    private function cardsForPrincipal(): array
    {
        return [
            [
                'key'   => 'students',
                'label' => 'Students',
                'value' => (string) Student::where('active', true)->count(),
                'icon'  => 'users',
            ],
            [
                'key'   => 'attendance',
                'label' => 'Attendance Today',
                'value' => $this->attendanceTodayPercent(),
                'icon'  => 'calendar-check',
            ],
            [
                'key'   => 'fees',
                'label' => 'Fees Collected (Term)',
                'value' => $this->feesCollected(),
                'icon'  => 'wallet',
            ],
            [
                'key'   => 'pending_approvals',
                'label' => 'Pending Approvals',
                'value' => (string) TimetableEntry::where('is_approved', false)->count(),
                'icon'  => 'clipboard-check',
            ],
        ];
    }

    private function cardsForBursar(): array
    {
        return [
            [
                'key'   => 'fees',
                'label' => 'Fees Collected',
                'value' => $this->feesCollected(),
                'icon'  => 'wallet',
            ],
            [
                'key'   => 'outstanding',
                'label' => 'Outstanding Balance',
                'value' => $this->outstandingBalance(),
                'icon'  => 'book-open',
            ],
            [
                'key'   => 'students',
                'label' => 'Students',
                'value' => (string) Student::where('active', true)->count(),
                'icon'  => 'users',
            ],
        ];
    }

    private function cardsForClassMaster($user): array
    {
        $classId = $this->classIdForTeacher($user);
        $studentCount = Student::where('active', true)
            ->where('current_class_id', $classId)
            ->count();

        return [
            [
                'key'   => 'my_class_students',
                'label' => 'My Class Students',
                'value' => (string) $studentCount,
                'icon'  => 'users',
            ],
            [
                'key'   => 'attendance',
                'label' => 'Attendance Today',
                'value' => $this->attendanceTodayPercent($classId),
                'icon'  => 'calendar-check',
            ],
        ];
    }

    private function cardsForSubjectTeacher($user): array
    {
        $subjectIds = Mark::where('owner_teacher_id', $user->id)
            ->distinct('subject_id')
            ->pluck('subject_id');

        $marksCount = Mark::where('owner_teacher_id', $user->id)->count();

        return [
            [
                'key'   => 'my_subjects',
                'label' => 'My Subjects',
                'value' => (string) $subjectIds->count(),
                'icon'  => 'book-open',
            ],
            [
                'key'   => 'marks',
                'label' => 'Marks Submitted',
                'value' => (string) $marksCount,
                'icon'  => 'award',
            ],
        ];
    }

    private function cardsForSecretary(): array
    {
        return [
            [
                'key'   => 'students',
                'label' => 'Students',
                'value' => (string) Student::where('active', true)->count(),
                'icon'  => 'users',
            ],
        ];
    }

    private function cardsForDisciplineMaster(): array
    {
        return [
            [
                'key'   => 'attendance',
                'label' => 'Attendance Today',
                'value' => $this->attendanceTodayPercent(),
                'icon'  => 'calendar-check',
            ],
            [
                'key'   => 'absentees',
                'label' => 'Absentees Today',
                'value' => $this->absenteesToday(),
                'icon'  => 'bell',
            ],
        ];
    }

    private function cardsForDefault(): array
    {
        return [
            [
                'key'   => 'students',
                'label' => 'Students',
                'value' => (string) Student::where('active', true)->count(),
                'icon'  => 'users',
            ],
        ];
    }

    private function attendanceTodayPercent(?string $classId = null): string
    {
        $today = now()->toDateString();
        $query = AttendanceSession::whereDate('opened_at', $today);

        if ($classId) {
            $query->where('class_id', $classId);
        }

        $sessions = $query->pluck('id');
        if ($sessions->isEmpty()) {
            return '0%';
        }

        $totalHeadcount = AttendanceSession::whereDate('opened_at', $today)
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->sum('headcount');

        $totalScanned = AttendanceEvent::whereIn('session_id', $sessions)
            ->whereNotIn('status', ['voided'])
            ->count();

        if ($totalHeadcount === 0) {
            return '0%';
        }

        $pct = round(($totalScanned / $totalHeadcount) * 100);
        return "{$pct}%";
    }

    private function feesCollected(): string
    {
        $total = (int) IssueEvent::where('status', 'issued')
            ->sum('cost');

        if ($total === 0) {
            return '0 XAF';
        }

        return number_format($total, 0, '.', ',') . ' XAF';
    }

    private function outstandingBalance(): string
    {
        $issued = (int) IssueEvent::where('status', 'issued')->sum('cost');
        $paid = (int) LedgerEntry::sum('amount');

        $balance = max(0, $issued - $paid);
        if ($balance === 0) {
            return '0 XAF';
        }

        return number_format($balance, 0, '.', ',') . ' XAF';
    }

    private function absenteesToday(): string
    {
        $today = now()->toDateString();
        $sessions = AttendanceSession::whereDate('opened_at', $today)->pluck('id');

        if ($sessions->isEmpty()) {
            return '0';
        }

        $totalHeadcount = AttendanceSession::whereDate('opened_at', $today)->sum('headcount');
        $totalScanned = AttendanceEvent::whereIn('session_id', $sessions)
            ->whereNotIn('status', ['voided'])
            ->count();

        return (string) max(0, $totalHeadcount - $totalScanned);
    }

    private function classIdForTeacher($user): ?string
    {
        $student = Student::where('current_class_id', '!=', null)->first();
        return $student?->current_class_id;
    }
}
