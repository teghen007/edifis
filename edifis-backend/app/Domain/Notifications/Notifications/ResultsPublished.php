<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class ResultsPublished extends Notification
{
    use Queueable;

    public function __construct(
        public string $studentId,
        public string $studentName,
        public string $sequence,
        public float $average,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', \App\Domain\Notifications\Channels\FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Results Published — EDIFIS',
            'body' => "{$this->studentName}: Average {$this->average} for {$this->sequence}",
            'data' => ['student_id' => $this->studentId, 'action' => 'results'],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Results Published',
            'body' => "Results for {$this->studentName} ({$this->sequence}) are available. Average: {$this->average}",
            'action' => 'results',
            'student_id' => $this->studentId,
        ];
    }
}
