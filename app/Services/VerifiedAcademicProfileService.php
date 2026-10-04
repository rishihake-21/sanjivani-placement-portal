<?php

namespace App\Services;

use App\Enums\AcademicLevel;
use App\Enums\RecordStatus;
use App\Models\Student;
use App\Support\VerifiedAcademicSnapshot;
use Illuminate\Support\Collection;

/**
 * REPLACES the student-module file of the same name. Behaviour is unchanged; the snapshot logic
 * moved into fromRecords() so the eligibility engine can build snapshots for many students from
 * ONE preloaded query instead of one query per student.
 *
 * It reads status = 'VERIFIED' rows and nothing else.
 *
 * CGPA: if the latest verified semester carries the university's printed CGPA, that value wins;
 * otherwise the plain mean of verified SGPAs.
 * Backlogs: total = sum of backlogs_in_term over verified semesters;
 *           active = active_backlogs_after_term of the latest verified semester.
 */
class VerifiedAcademicProfileService
{
    public function forStudent(Student $student): VerifiedAcademicSnapshot
    {
        return $this->fromRecords($student, $student->academicRecords()->verified()->get());
    }

    /** @param  Collection<int, \App\Models\AcademicRecord>  $records  any records of this student; non-VERIFIED rows are ignored */
    public function fromRecords(Student $student, Collection $records): VerifiedAcademicSnapshot
    {
        $verified = $records->filter(fn ($r) => $r->status === RecordStatus::VERIFIED);
        $byKey = $verified->keyBy(fn ($r) => $r->level->value . ':' . ($r->semester ?? 0));

        $missing = [];
        foreach ($student->requiredAcademicKeys() as [$level, $semester]) {
            if (! $byKey->has($level->value . ':' . ($semester ?? 0))) {
                $missing[] = $level->label($semester);
            }
        }

        $semesters = $verified->filter(fn ($r) => $r->level === AcademicLevel::DEGREE_SEM)->sortBy('semester')->values();
        $latest = $semesters->last();

        $cgpa = null;
        if ($latest !== null) {
            $cgpa = $latest->cgpa !== null
                ? (float) $latest->cgpa
                : round((float) $semesters->avg(fn ($r) => (float) $r->sgpa), 2);
        }

        $percentage = fn (string $key): ?float => $byKey->has($key) ? (float) $byKey->get($key)->percentage : null;

        return new VerifiedAcademicSnapshot(
            tenthPercentage: $percentage('TENTH:0'),
            twelfthPercentage: $percentage('TWELFTH:0'),
            diplomaPercentage: $percentage('DIPLOMA:0'),
            cgpa: $cgpa,
            activeBacklogs: $latest ? (int) $latest->active_backlogs_after_term : 0,
            totalBacklogs: (int) $semesters->sum('backlogs_in_term'),
            missing: $missing,
        );
    }
}
