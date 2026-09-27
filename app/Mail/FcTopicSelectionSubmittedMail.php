<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FcTopicSelectionSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public array $topicNames,
        public string $actionUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'มีหัวข้อ FC รอการอนุมัติ',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.fc-topic-selection-submitted',
            with: [
                'employee' => $this->employee,
                'topicNames' => $this->topicNames,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
