<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FcTopicSelectionStatusUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public array $topicNames,
        public string $status,
        public string $actionUrl,
        public string $comment = '',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'อัปเดตสถานะหัวข้อ FC',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.fc-topic-selection-status-update',
            with: [
                'employee' => $this->employee,
                'topicNames' => $this->topicNames,
                'status' => $this->status,
                'actionUrl' => $this->actionUrl,
                'comment' => $this->comment,
            ],
        );
    }
}
