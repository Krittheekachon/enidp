<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewUserDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $users)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'สรุปผู้ใช้งานใหม่');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digests.new-users',
            with: [
                'users' => $this->users,
                'actionUrl' => route('dashboard'),
            ],
        );
    }
}

