<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * System-generated content-safety notice sent to a user by email (Gmail SMTP).
 */
class AccountNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $title,
        public string $guidance,
        public string $severity = 'info',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-notice',
            with: [
                'title' => $this->title,
                'guidance' => $this->guidance,
                'severity' => $this->severity,
            ],
        );
    }
}