<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentAbsent extends Notification
{
    use Queueable;

    public function __construct(
        public string $studentId,
        public string $studentName,
        public string $date,
        public ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', \App\Domain\Notifications\Channels\FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        $body = "{$this->studentName} was marked absent today"
            . ($this->reason ? " ({$this->reason})" : '') . '.';

        return [
            'title' => 'Attendance — EDIFIS',
            'body' => $body,
            'data' => ['student_id' => $this->studentId, 'action' => 'attendance'],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Absence recorded',
            'body' => "{$this->studentName} was marked absent on {$this->date}"
                . ($this->reason ? " — {$this->reason}" : '') . '.',
            'action' => 'attendance',
            'student_id' => $this->studentId,
        ];
    }
}
