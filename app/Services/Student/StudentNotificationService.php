<?php

namespace App\Services\Student;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read model for a student's own notifications. Returns the plain rows the notifications endpoint
 * sends; portal pages add their display-only extras (the readable rejection label) on top.
 */
class StudentNotificationService
{
    public function recent(User $user, int $limit = 50): Collection
    {
        return $user->notifications()->latest()->limit($limit)->get()
            ->map(fn ($n) => ['id' => $n->id, 'read' => $n->read_at !== null, 'created_at' => $n->created_at] + $n->data);
    }
}
