<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IdpProgressApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public string $competencyName,
        public string $achievementStatus,
        public string $comment,
        public string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ผลความก้าวหน้า IDP ได้รับการอนุมัติแล้ว',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.idp-progress-approved',
        );
    }
}
