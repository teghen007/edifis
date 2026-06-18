<?php

namespace App\Livewire\Field;

use App\Domain\Attendance\Actions\OpenSession;
use App\Domain\Attendance\Actions\RecordScan;
use App\Domain\Attendance\Actions\CloseSession;
use App\Domain\Attendance\Actions\VoidScan;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Attendance\Models\AttendanceEvent;
use Livewire\Component;

class AttendanceScanner extends Component
{
    public ?string $sessionId = null;
    public string $classId = '';
    public string $subjectId = '';
    public string $period = 'AM';
    public ?int $headcount = null;

    public string $scanStudentId = '';
    public string $scanSource = 'qr_scan';
    public string $overrideReason = '';

    public ?array $tally = null;
    public bool $sessionOpen = false;
    public ?string $lastScanStatus = null;

    public function mount(): void
    {
        $this->period = now()->hour < 12 ? 'AM' : 'PM';
    }

    public function openSession(OpenSession $open): void
    {
        $this->validate([
            'classId' => 'required|uuid',
            'subjectId' => 'required|uuid',
            'period' => 'required|string',
        ]);

        $session = $open->handle(
            classId: $this->classId,
            subjectId: $this->subjectId,
            period: $this->period,
            teacherId: auth()->id(),
        );

        $this->sessionId = $session->id;
        $this->sessionOpen = true;
        $this->refreshTally();
    }

    public function scan(RecordScan $scan): void
    {
        if (! $this->sessionId) return;

        $this->validate(['scanStudentId' => 'required|string']);

        $source = $this->scanSource;
        $reason = null;

        if ($source === 'manual_override') {
            $this->validate(['overrideReason' => 'required|string|min:3']);
            $reason = $this->overrideReason;
        }

        $result = $scan->handle(
            sessionId: $this->sessionId,
            studentId: $this->scanStudentId,
            source: $source,
            voidReason: $reason,
            deviceId: 'web-' . auth()->id(),
            scannedBy: auth()->id(),
        );

        $this->lastScanStatus = ($result['status'] ?? null) === 'replay' ? 'replay' : 'scanned';
        $this->scanStudentId = '';
        $this->overrideReason = '';
        $this->scanSource = 'qr_scan';
        $this->refreshTally();
    }

    public function close(CloseSession $close): void
    {
        if (! $this->sessionId) return;

        $close->handle($this->sessionId);
        $this->sessionOpen = false;
        $this->refreshTally();
    }

    public function voidScan(VoidScan $void, string $eventId, string $reason): void
    {
        $void->handle(eventId: $eventId, reason: $reason, actorId: auth()->id());
        $this->refreshTally();
    }

    private function refreshTally(): void
    {
        if (! $this->sessionId) {
            $this->tally = null;
            return;
        }

        $scanned = AttendanceEvent::where('session_id', $this->sessionId)
            ->where('status', 'present')
            ->count();

        $this->tally = [
            'scanned' => $scanned,
            'headcount' => $this->headcount,
        ];
    }

    public function getRecentEventsProperty()
    {
        if (! $this->sessionId) return collect();

        return AttendanceEvent::where('session_id', $this->sessionId)
            ->latest()
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('livewire.field.attendance-scanner')
            ->layout('components.layouts.app', ['title' => 'Attendance — EDIFIS Field']);
    }
}
