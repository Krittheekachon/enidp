<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IdpSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public string $competencyName,
        public string $actionUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'มีแผน IDP รอการอนุมัติ',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.idp-submitted',
            with: [
                'employee' => $this->employee,
                'competencyName' => $this->competencyName,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
