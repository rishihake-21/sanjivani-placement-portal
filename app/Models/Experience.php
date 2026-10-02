<?php

namespace App\Models;

use App\Enums\ExperienceType;
use App\Enums\RecordStatus;
use App\Enums\RejectionReason;
use App\Models\Concerns\Reviewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Experience extends Model
{
    use Reviewable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ExperienceType::class,
            'status' => RecordStatus::class,
            'rejection_reason_code' => RejectionReason::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_ongoing' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'certificate_document_id');
    }

    // ---- Reviewable contract -------------------------------------------------

    public function proofDocument(): ?Document
    {
        return $this->certificate;
    }

    public function proofColumn(): string
    {
        return 'certificate_document_id';
    }

    public function label(): string
    {
        return $this->type->value . ' at ' . $this->organization;
    }

    public function verificationAttributes(): array
    {
        return [];
    }
}
