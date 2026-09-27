<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnmappedPositionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $positionName,
        public string $actionUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'มีตำแหน่งที่ยังไม่ผูกสมรรถนะ',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.unmapped-position',
            with: [
                'positionName' => $this->positionName,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}

