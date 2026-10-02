<?php

namespace App\Models;

use App\Enums\AcademicLevel;
use App\Enums\AdmissionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    /** Identity/admission columns are system-controlled: never mass-assigned from a request. */
    protected $guarded = ['id', 'user_id', 'master_list_id', 'university_id', 'department_id', 'branch_id',
        'admission_type', 'admission_year', 'graduation_year', 'current_semester', 'profile_locked_at'];

    protected function casts(): array
    {
        return [
            'admission_type' => AdmissionType::class,
            'date_of_birth' => 'date',
            'identity_confirmed_at' => 'datetime',
            'profile_locked_at' => 'datetime',
            'skills' => 'array',
            'languages' => 'array',
            'preferred_roles' => 'array',
            'preferred_locations' => 'array',
            'opted_out_of_placement' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicRecords(): HasMany
    {
        return $this->hasMany(AcademicRecord::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeInDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function isLocked(): bool
    {
        return $this->profile_locked_at !== null;
    }

    public function isLateral(): bool
    {
        return $this->admission_type === AdmissionType::LATERAL;
    }

    /**
     * Academic rows that MUST be verified before the student can apply anywhere.
     *  - regular : 10th, 12th, semesters 1 .. current-1
     *  - lateral : 10th, Diploma, semesters 3 .. current-1  (12th optional)
     * The current semester has no result yet, so it is never required.
     *
     * @return list<array{0: AcademicLevel, 1: int|null}>
     */
    public function requiredAcademicKeys(): array
    {
        $keys = [[AcademicLevel::TENTH, null]];
        $keys[] = $this->isLateral() ? [AcademicLevel::DIPLOMA, null] : [AcademicLevel::TWELFTH, null];

        for ($sem = $this->admission_type->firstSemester(); $sem < $this->current_semester; $sem++) {
            $keys[] = [AcademicLevel::DEGREE_SEM, $sem];
        }

        return $keys;
    }

    /** Which levels / semesters this student is allowed to create a record for. */
    public function allowsAcademicKey(AcademicLevel $level, ?int $semester): bool
    {
        return match ($level) {
            AcademicLevel::TENTH, AcademicLevel::TWELFTH => true,
            AcademicLevel::DIPLOMA => $this->isLateral(),
            AcademicLevel::DEGREE_SEM => $semester !== null
                && $semester >= $this->admission_type->firstSemester()
                && $semester <= $this->current_semester,
        };
    }
}
