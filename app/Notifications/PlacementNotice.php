<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * One in-app notification type for the whole placement flow.
 * kind: drive_published | drive_cancelled | stage_changed | offer_recorded | placed | offer_declined
 */
class PlacementNotice extends Notification
{
    public function __construct(
        private string $kind,
        private string $title,
        private ?string $body = null,
        private array $data = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body] + $this->data;
    }
}
