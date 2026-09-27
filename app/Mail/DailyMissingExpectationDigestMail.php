<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyMissingExpectationDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $levels)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'สรุปประจำวัน: ระดับตำแหน่งยังไม่ได้ตั้งค่าความคาดหวัง');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digests.daily-missing-expectations',
            with: [
                'levels' => $this->levels,
                'actionUrl' => route('dashboard'),
            ],
        );
    }
}

