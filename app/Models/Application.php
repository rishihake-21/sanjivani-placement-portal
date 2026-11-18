<?php

namespace App\Models;

use App\Enums\ApplicationStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stage' => ApplicationStage::class,
            'applied_at' => 'datetime',
            'stage_updated_at' => 'datetime',
            'data_snapshot' => 'array',
        ];
    }

    public function drive(): BelongsTo
    {
        return $this->belongsTo(PlacementDrive::class, 'drive_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * The single definition of "an applicant of this recruiter": applied to a published or closed drive
     * of the recruiter's own company, and has not withdrawn. Every recruiter query and the document
     * policy go through here, so the boundary cannot drift between endpoints.
     */
    public function scopeVisibleToRecruiter(Builder $query, Recruiter $recruiter): Builder
    {
        return $query
            ->where('stage', '<>', ApplicationStage::WITHDRAWN->value)
            ->whereHas('drive', fn (Builder $d) => $d
                ->where('company_id', $recruiter->company_id)
                ->whereIn('status', config('tpms.recruiter.visible_drive_statuses')));
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->whereHas('student', fn (Builder $s) => $s->where('department_id', $departmentId));
    }
}
