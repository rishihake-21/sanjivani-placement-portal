<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(
        string $action,
        Model $subject,
        ?int $studentId,
        ?array $old = null,
        ?array $new = null,
        ?User $actor = null,
        array $meta = [],
    ): AuditLog {
        $actor ??= auth()->user();
        $request = request();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role?->value,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'student_id' => $studentId,
            'old_values' => $old,
            'new_values' => $new,
            'meta' => $meta + [
                'ip' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            ],
        ]);
    }

    /** Model attributes without noise, safe to store as JSON. */
    public static function snapshot(Model $model): array
    {
        return array_diff_key($model->attributesToArray(), array_flip(['created_at', 'updated_at']));
    }
}
