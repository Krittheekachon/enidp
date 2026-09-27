<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoleNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $title,
        public string $body,
        public string $buttonText,
        public string $actionUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.role-notification',
            with: [
                'title' => $this->title,
                'body' => $this->body,
                'buttonText' => $this->buttonText,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}

