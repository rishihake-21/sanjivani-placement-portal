<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** In-app (database) notification telling a student what the coordinator decided. */
class RecordReviewed extends Notification
{
    public function __construct(
        private string $subject,      // e.g. "Semester 3", "INTERNSHIP at Acme", "Resume"
        private string $decision,     // APPROVED | REJECTED | UNLOCKED
        private ?string $reasonCode = null,
        private ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subject' => $this->subject,
            'decision' => $this->decision,
            'reason_code' => $this->reasonCode,
            'reason' => $this->reason,
        ];
    }
}
