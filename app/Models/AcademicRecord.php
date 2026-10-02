<?php

namespace App\Models;

use App\Enums\AcademicLevel;
use App\Enums\RecordStatus;
use App\Enums\RejectionReason;
use App\Models\Concerns\Reviewable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicRecord extends Model
{
    use Reviewable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'level' => AcademicLevel::class,
            'status' => RecordStatus::class,
            'rejection_reason_code' => RejectionReason::class,
            'percentage' => 'decimal:2',
            'obtained_marks' => 'decimal:2',
            'total_marks' => 'decimal:2',
            'sgpa' => 'decimal:2',
            'cgpa' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function scopeForKey(Builder $query, AcademicLevel $level, ?int $semester): Builder
    {
        return $query->where('level', $level->value)
            ->when(
                $semester === null,
                fn (Builder $q) => $q->whereNull('semester'),
                fn (Builder $q) => $q->where('semester', $semester)
            );
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    // ---- Reviewable contract -------------------------------------------------

    public function proofDocument(): ?Document
    {
        return $this->document;
    }

    public function proofColumn(): string
    {
        return 'document_id';
    }

    public function label(): string
    {
        return $this->level->label($this->semester);
    }

    /**
     * Past terms are locked once verified so the student cannot silently change history.
     * The current semester stays editable (results are still coming in).
     */
    public function verificationAttributes(): array
    {
        $isCurrentTerm = $this->level === AcademicLevel::DEGREE_SEM
            && (int) $this->semester === (int) $this->student->current_semester;

        return ['locked_at' => $isCurrentTerm ? null : now()];
    }
}
