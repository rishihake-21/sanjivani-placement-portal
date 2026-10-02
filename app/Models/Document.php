<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\RejectionReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['disk', 'path', 'sha256'];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'is_primary' => 'boolean',
            'reviewed_at' => 'datetime',
            'rejection_reason_code' => RejectionReason::class,
        ];
    }

    /** The public identifier in URLs is the uuid, never the sequential id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isStandalone(): bool
    {
        return $this->document_type->isStandalone();
    }
}
