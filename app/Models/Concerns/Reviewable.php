<?php

namespace App\Models\Concerns;

use App\Enums\RecordStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared behaviour of rows that go through the student -> coordinator review lifecycle
 * (AcademicRecord, Experience). Host models must define:
 *   - student(): BelongsTo
 *   - proofDocument(): ?Document
 *   - proofColumn(): string            (column holding the supporting document id)
 *   - label(): string                  (human label used in notifications)
 *   - verificationAttributes(): array  (extra columns to set when verified)
 */
trait Reviewable
{
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::VERIFIED->value);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::PENDING->value);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', '<>', RecordStatus::SUPERSEDED->value);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(static::class, 'supersedes_id');
    }

    public function isStudentEditable(): bool
    {
        return $this->status->isStudentEditable();
    }
}
