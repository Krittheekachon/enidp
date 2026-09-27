<?php

namespace App\Services;

use App\Models\User;

class NotificationRecipientResolver
{
    public function recipientsFor(User $user): User|string|array|null
    {
        if ($this->sendToRealUsers()) {
            return $user->email ? $user : null;
        }

        $recipients = $this->devRecipients();

        return count($recipients) === 1 ? $recipients[0] : $recipients;
    }

    public function recipientLabelFor(User $user): string
    {
        $recipient = $this->recipientsFor($user);

        if ($recipient instanceof User) {
            return (string) $recipient->email;
        }

        if (is_array($recipient)) {
            return implode(',', $recipient);
        }

        return (string) $recipient;
    }

    private function sendToRealUsers(): bool
    {
        return (bool) config('mail.send_to_real_users', false);
    }

    private function devRecipients(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            (array) config('mail.dev_to', []),
        )));
    }
}
