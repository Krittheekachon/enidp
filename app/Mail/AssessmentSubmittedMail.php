<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssessmentSubmittedMail extends Mailable
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
            subject: 'มีผลการประเมินรอการอนุมัติ',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assessment-submitted',
            with: [
                'employee' => $this->employee,
                'competencyName' => $this->competencyName,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}

