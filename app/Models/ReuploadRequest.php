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
}
