<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingAssessmentDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $users)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'สรุปประจำวัน: บุคลากรยังไม่ประเมินตนเอง');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digests.pending-assessments',
            with: [
                'users' => $this->users,
                'actionUrl' => route('dashboard'),
            ],
        );
    }
}

