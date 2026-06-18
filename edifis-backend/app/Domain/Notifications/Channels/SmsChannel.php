<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

/** EMPTY stub only. SMS is out of pilot (ADR-018). Never wire to a via(). */
interface SmsChannel
{
    public function send($notifiable, $notification): void;
}
