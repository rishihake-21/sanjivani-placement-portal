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

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'drive_departments', 'drive_id', 'department_id');
    }

    /**
     * Resolve department targeting for modern drives and legacy drives that were created
     * with branch_ids before the programme hierarchy existed.
     *
     * @return list<int>
     */
    public function targetDepartmentIds(): array
    {
        $this->loadMissing(['departments', 'branches']);

        // The new department pivot is authoritative for modern drives. Legacy branch-only
        // drives are resolved below for compatibility with old clients and existing tests.
        if ($this->departments->isNotEmpty()) {
            return $this->departments->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        return $this->branches->map(function (Branch $branch) {
            if ($branch->code === 'CSE-AIML') {
                $programId = Department::query()->whereKey($branch->department_id)->value('program_id');

                return Department::query()->where('program_id', $programId)->where('code', 'AIML')->value('id');
            }

            return $branch->department_id;
        })->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'drive_id');
    }

    /** Drives a coordinator may see: already published (or closed) AND targeting their department. */
    public function scopeVisibleToDepartment(Builder $query, int $departmentId): Builder
    {
        return $query
            ->whereIn('status', [DriveStatus::PUBLISHED->value, DriveStatus::CLOSED->value])
            ->where(function (Builder $target) use ($departmentId) {
                $target->whereHas('departments', fn (Builder $d) => $d->where('departments.id', $departmentId))
                    ->orWhere(function (Builder $legacy) use ($departmentId) {
                        $legacy->whereDoesntHave('departments')
                            ->whereHas('branches', function (Builder $branch) use ($departmentId) {
                                $branch->where('branches.department_id', $departmentId)
                                    ->where('branches.code', '<>', 'CSE-AIML');
                            });
                    });
            });
    }
}
