<?php

namespace App\Models;

use App\Enums\PlacementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Placement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PlacementStatus::class,
            'ctc_lpa' => 'decimal:2',
            'offer_date' => 'date',
            'joining_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->whereHas('student', fn (Builder $s) => $s->where('department_id', $departmentId));
    }
}
