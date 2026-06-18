<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OnboardingApproved extends Mailable
{
    public function __construct(
        public string $schoolName,
        public string $schoolCode,
        public string $claimCode,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "EDIFIS — {$this->schoolName} is now live");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.onboarding-approved',
            with: [
                'schoolName' => $this->schoolName,
                'schoolCode' => $this->schoolCode,
                'claimCode' => $this->claimCode,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
