<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class FeePosted extends Notification
{
    use Queueable;

    public function __construct(
        public string $studentId,
        public string $studentName,
        public int $amount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', \App\Domain\Notifications\Channels\FcmChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Fee Posted',
            'body' => "A fee of {$this->amount} XAF was posted for {$this->studentName}.",
            'action' => 'fees',
            'student_id' => $this->studentId,
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Fee Posted — EDIFIS')
            ->body("{$this->studentName}: {$this->amount} XAF posted")
            ->action('View Fees', 'fees')
            ->data(['student_id' => $this->studentId]);
    }
}

class AttendanceFlagged extends Notification
{
    use Queueable;

    public function __construct(
        public string $studentId,
        public string $studentName,
        public int $absences,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', \App\Domain\Notifications\Channels\FcmChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Attendance Flagged',
            'body' => "{$this->studentName}: {$this->absences} absence(s) this term.",
            'action' => 'attendance',
            'student_id' => $this->studentId,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Attendance — EDIFIS',
            'body' => "{$this->studentName}: {$this->absences} absence(s)",
            'data' => ['student_id' => $this->studentId, 'action' => 'attendance'],
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Attendance — EDIFIS')
            ->body("{$this->studentName}: {$this->absences} absence(s)")
            ->action('View Attendance', 'attendance')
            ->data(['student_id' => $this->studentId]);
    }
}

class ExeatIssued extends Notification
{
    use Queueable;

    public function __construct(
        public string $studentId,
        public string $studentName,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', \App\Domain\Notifications\Channels\FcmChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Exeat Issued',
            'body' => "Exeat for {$this->studentName}: {$this->reason}",
            'action' => 'exeat',
            'student_id' => $this->studentId,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Exeat Issued — EDIFIS',
            'body' => "{$this->studentName}: {$this->reason}",
            'data' => ['student_id' => $this->studentId, 'action' => 'exeat'],
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Exeat Issued — EDIFIS')
            ->body("{$this->studentName}: {$this->reason}")
            ->action('View Details', 'exeat')
            ->data(['student_id' => $this->studentId]);
    }
}

class CalendarEventPosted extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $startsAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', \App\Domain\Notifications\Channels\FcmChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Calendar Event',
            'body' => "{$this->title} — {$this->startsAt}",
            'action' => 'calendar',
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'EDIFIS Calendar',
            'body' => "{$this->title} — {$this->startsAt}",
            'data' => ['action' => 'calendar'],
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('EDIFIS Calendar')
            ->body("{$this->title} — {$this->startsAt}")
            ->action('View Calendar', 'calendar');
    }
}
