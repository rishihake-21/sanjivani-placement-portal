<?php

namespace App\Models;

use App\Enums\ReuploadStatus;
use App\Enums\ReuploadSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReuploadRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subject_type' => ReuploadSubject::class,
            'status' => ReuploadStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Close open requests when the student has submitted a new version.
     * @param  list<int|null>  $subjectIds  the row itself and (for revisions) the row it supersedes
     */
    public static function fulfil(ReuploadSubject $type, array $subjectIds): int
    {
        $ids = array_values(array_filter($subjectIds, fn ($id) => $id !== null));
        if ($ids === []) {
            return 0;
        }

        return static::query()
            ->where('status', ReuploadStatus::OPEN->value)
            ->where('subject_type', $type->value)
            ->whereIn('subject_id', $ids)
            ->update(['status' => ReuploadStatus::FULFILLED->value, 'closed_at' => now(), 'updated_at' => now()]);
    }
}
