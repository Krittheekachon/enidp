<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderAssessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public string $actionUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'แจ้งเตือน: กรุณาประเมินตนเอง',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder-assess',
            with: [
                'employee' => $this->employee,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}

