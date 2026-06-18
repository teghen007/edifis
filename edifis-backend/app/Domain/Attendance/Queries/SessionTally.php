<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Queries;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceSession;

class SessionTally
{
    public function get(string $sessionId): array
    {
        $session = AttendanceSession::findOrFail($sessionId);

        $scanned = AttendanceEvent::where('session_id', $sessionId)
            ->where('status', 'present')
            ->count();

        // Append-only: voids are separate events, never mutate originals
        // Tally = present scans, voided ones surface in audit but don't delete the original
        return [
            'session_id' => $sessionId,
            'scanned' => $scanned,
            'headcount' => $session->headcount,
        ];
    }
}
