<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnmappedPositionUserDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $users,
        public string $frequency = 'daily',
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->frequency === 'hourly'
            ? 'สรุปรายชั่วโมง: ผู้ใช้ใหม่ที่ตำแหน่งยังไม่ผูกสมรรถนะ'
            : 'สรุปประจำวัน: ผู้ใช้ที่ตำแหน่งยังไม่ผูกสมรรถนะ';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digests.unmapped-position-users',
            with: [
                'users' => $this->users,
                'frequency' => $this->frequency,
                'actionUrl' => route('dashboard'),
            ],
        );
    }
}

