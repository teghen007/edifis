<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpMail extends Mailable
{
    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'EDIFIS — Your Login Code');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.otp',
            with: ['code' => $this->code],
        );
    }
}
