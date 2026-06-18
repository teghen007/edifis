<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\AttendanceSession;

class CloseSession
{
    public function handle(string $sessionId): AttendanceSession
    {
        $session = AttendanceSession::findOrFail($sessionId);
        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return $session;
    }
}
