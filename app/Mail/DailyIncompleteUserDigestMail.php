<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyIncompleteUserDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $users)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'สรุปประจำวัน: ผู้ใช้งานข้อมูลไม่ครบ');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digests.daily-incomplete-users',
            with: [
                'users' => $this->users,
                'actionUrl' => route('dashboard'),
            ],
        );
    }
}

