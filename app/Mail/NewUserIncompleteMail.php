<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewUserIncompleteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $actionUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'มีผู้ใช้งานใหม่ข้อมูลไม่ครบ',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-user-incomplete',
            with: [
                'user' => $this->user,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}

