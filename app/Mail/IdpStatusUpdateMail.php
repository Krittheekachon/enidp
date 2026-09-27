<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IdpStatusUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public string $competencyName,
        public string $status,
        public string $actionUrl,
        public string $rejectComment = '',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'อัปเดตสถานะแผน IDP',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.idp-status-update',
            with: [
                'employee' => $this->employee,
                'competencyName' => $this->competencyName,
                'status' => $this->status,
                'actionUrl' => $this->actionUrl,
                'rejectComment' => $this->rejectComment,
            ],
        );
    }
}
