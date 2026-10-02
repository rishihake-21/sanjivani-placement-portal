<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\ProfileCompletenessService;
use App\Services\VerifiedAcademicProfileService;

trait PresentsStudentDetail
{
    /** Full read-only view of one student: profile, records, documents, completeness, verified numbers. */
    protected function studentDetail(Student $student): array
    {
        $student->load([
            'department', 'branch',
            'academicRecords' => fn ($q) => $q->live()->with('document')->orderByRaw("array_position(ARRAY['TENTH','TWELFTH','DIPLOMA','DEGREE_SEM']::text[], level::text)")->orderBy('semester')->orderByDesc('version'),
            'experiences' => fn ($q) => $q->live()->with('certificate')->orderByDesc('start_date'),
            'documents' => fn ($q) => $q->where('status', '<>', 'SUPERSEDED')->orderBy('document_type'),
        ]);

        return [
            'student' => new StudentResource($student),
            'completeness' => app(ProfileCompletenessService::class)->summarize($student),
            'verified_academics' => app(VerifiedAcademicProfileService::class)->forStudent($student)->toArray(),
        ];
    }
}
