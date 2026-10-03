<?php

namespace App\Models;

use App\Enums\DriveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlacementDrive extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DriveStatus::class,
            'drive_date' => 'date',
            'application_deadline' => 'datetime',
            'published_at' => 'datetime',
            'ctc_lpa' => 'decimal:2',
            'ctc_max_lpa' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function eligibility(): HasOne
    {
        return $this->hasOne(DriveEligibility::class, 'drive_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'drive_branches', 'drive_id', 'branch_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'drive_id');
    }

    /** Drives a coordinator may see: already published (or closed) AND targeting a branch of their department. */
    public function scopeVisibleToDepartment(Builder $query, int $departmentId): Builder
    {
        return $query
            ->whereIn('status', [DriveStatus::PUBLISHED->value, DriveStatus::CLOSED->value])
            ->whereHas('branches', fn (Builder $b) => $b->where('branches.department_id', $departmentId));
    }
}
