<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminDailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $sections)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'สรุปประจำวัน: รายการที่ต้องดำเนินการ');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digests.admin-daily',
            with: [
                'sections' => $this->sections,
                'actionUrl' => route('dashboard'),
            ],
        );
    }
}

