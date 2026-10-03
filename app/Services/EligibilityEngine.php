<?php

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Support\EligibilityResult;
use App\Support\VerifiedAcademicSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pure rule check: student x drive -> EligibilityResult (with a reason for every failed or unverified criterion).
 *
 *  - Uses VERIFIED academic values only (via VerifiedAcademicProfileService).
 *  - Only criteria the drive actually sets are checked; a drive that only needs CGPA does not wait for 10th.
 *  - Never frozen: callers evaluate when a student views a drive and again when they apply.
 *  - Lateral entry: min 12th falls back to the diploma percentage (drive_eligibility.diploma_counts_as_twelfth);
 *    a min diploma % does not apply to regular students.
 *  - Profile completeness (identity confirmed, approved resume) is NOT part of this; ApplicationService checks it at apply time.
 */
class EligibilityEngine
{
    public function __construct(private VerifiedAcademicProfileService $academics)
    {
    }

    public function evaluate(Student $student, PlacementDrive $drive): EligibilityResult
    {
        $drive->loadMissing(['eligibility', 'branches']);

        $snapshot = $this->academics->fromRecords($student, $student->academicRecords()->verified()->get());
        $placed = Placement::query()->where('student_id', $student->id)->where('status', 'PLACED')->exists();

        return $this->check($student, $drive, $snapshot, $placed);
    }

    /**
     * Same check for many students with two queries in total.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, EligibilityResult>  keyed by student id
     */
    public function evaluateMany(Collection $students, PlacementDrive $drive): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        $drive->loadMissing(['eligibility', 'branches']);
        $ids = $students->pluck('id')->all();

        $records = AcademicRecord::query()->verified()->whereIn('student_id', $ids)->get()->groupBy('student_id');
        $placedIds = Placement::query()->where('status', 'PLACED')->whereIn('student_id', $ids)->pluck('student_id')->flip();

        $results = [];
        foreach ($students as $student) {
            $results[$student->id] = $this->check(
                $student,
                $drive,
                $this->academics->fromRecords($student, $records->get($student->id, collect())),
                $placedIds->has($student->id),
            );
        }

        return $results;
    }

    /** Students a drive is aimed at: its graduation year and its target branches. */
    public function candidateQuery(PlacementDrive $drive): Builder
    {
        $drive->loadMissing('branches');

        return Student::query()
            ->where('graduation_year', $drive->graduation_year)
            ->whereIn('branch_id', $drive->branches->pluck('id'));
    }

    // ------------------------------------------------------------------------------------------------

    private function check(Student $student, PlacementDrive $drive, VerifiedAcademicSnapshot $snap, bool $placed): EligibilityResult
    {
        $checks = [];
        $branchIds = $drive->branches->pluck('id')->map(fn ($id) => (int) $id)->all();

        $checks[] = $this->boolean('graduation_year', 'Graduation year', $drive->graduation_year, $student->graduation_year,
            (int) $student->graduation_year === (int) $drive->graduation_year,
            'This drive is for the ' . $drive->graduation_year . ' batch.');
        $checks[] = $this->boolean('branch', 'Branch', null, $student->branch_id,
            in_array((int) $student->branch_id, $branchIds, true),
            'Your branch is not targeted by this drive.');
        $checks[] = $this->boolean('placement_opt_out', 'Placement opt-out', false, (bool) $student->opted_out_of_placement,
            ! $student->opted_out_of_placement,
            'You have opted out of campus placements.');
        if (! $drive->allow_placed_students) {
            $checks[] = $this->boolean('already_placed', 'Already placed', false, $placed, ! $placed,
                'You are already placed, and this drive does not accept placed students.');
        }

        $e = $drive->eligibility;
        if ($e !== null) {
            $semestersUnverified = collect($snap->missing)->contains(fn (string $m) => str_starts_with($m, 'Semester'));

            if ($e->min_cgpa !== null) {
                $checks[] = $this->threshold('min_cgpa', 'CGPA', (float) $e->min_cgpa, $snap->cgpa, 'no verified semester result yet');
            }
            if ($e->min_tenth_percentage !== null) {
                $checks[] = $this->threshold('min_tenth_percentage', '10th %', (float) $e->min_tenth_percentage, $snap->tenthPercentage, '10th marksheet not verified yet');
            }
            if ($e->min_twelfth_percentage !== null) {
                $checks[] = $this->twelfth($student, $snap, $e);
            }
            if ($e->min_diploma_percentage !== null) {
                $checks[] = $student->isLateral()
                    ? $this->threshold('min_diploma_percentage', 'Diploma %', (float) $e->min_diploma_percentage, $snap->diplomaPercentage, 'diploma marksheet not verified yet')
                    : $this->result('min_diploma_percentage', 'Diploma %', (float) $e->min_diploma_percentage, null, 'NA', null);
            }
            if ($e->max_active_backlogs !== null) {
                $checks[] = $this->maximum('max_active_backlogs', 'Active backlogs', (int) $e->max_active_backlogs, $snap->activeBacklogs, $semestersUnverified);
            }
            if ($e->max_total_backlogs !== null) {
                $checks[] = $this->maximum('max_total_backlogs', 'Total backlogs', (int) $e->max_total_backlogs, $snap->totalBacklogs, $semestersUnverified);
            }
        }

        $results = array_column($checks, 'result');
        $status = match (true) {
            in_array('FAIL', $results, true) => EligibilityResult::NOT_ELIGIBLE,
            in_array('PENDING', $results, true) => EligibilityResult::PENDING,
            default => EligibilityResult::ELIGIBLE,
        };

        return new EligibilityResult($status, $checks);
    }

    private function twelfth(Student $student, VerifiedAcademicSnapshot $snap, $e): array
    {
        $required = (float) $e->min_twelfth_percentage;

        if ($snap->twelfthPercentage !== null) {
            return $this->threshold('min_twelfth_percentage', '12th %', $required, $snap->twelfthPercentage, '');
        }
        if (! $student->isLateral()) {
            return $this->threshold('min_twelfth_percentage', '12th %', $required, null, '12th marksheet not verified yet');
        }
        if ($e->diploma_counts_as_twelfth) {
            return $this->threshold('min_twelfth_percentage', '12th % (diploma accepted)', $required, $snap->diplomaPercentage, 'diploma marksheet not verified yet');
        }

        return $this->result('min_twelfth_percentage', '12th %', $required, null, 'FAIL',
            '12th marks are required for this drive and cannot be replaced by a diploma.');
    }

    private function threshold(string $criterion, string $label, float $required, ?float $actual, string $pendingText): array
    {
        if ($actual === null) {
            return $this->result($criterion, $label, $required, null, 'PENDING', "{$label}: {$pendingText}.");
        }
        if (round($actual, 2) >= round($required, 2)) {
            return $this->result($criterion, $label, $required, $actual, 'PASS', null);
        }

        return $this->result($criterion, $label, $required, $actual, 'FAIL',
            "{$label} is {$actual}; the drive requires at least {$required}.");
    }

    private function maximum(string $criterion, string $label, int $max, int $actual, bool $unverified): array
    {
        if ($unverified) {
            return $this->result($criterion, $label, $max, null, 'PENDING', "{$label}: semester results are not all verified yet.");
        }
        if ($actual <= $max) {
            return $this->result($criterion, $label, $max, $actual, 'PASS', null);
        }

        return $this->result($criterion, $label, $max, $actual, 'FAIL', "{$label}: you have {$actual}; the drive allows at most {$max}.");
    }

    private function boolean(string $criterion, string $label, mixed $required, mixed $actual, bool $passed, string $failMessage): array
    {
        return $this->result($criterion, $label, $required, $actual, $passed ? 'PASS' : 'FAIL', $passed ? null : $failMessage);
    }

    private function result(string $criterion, string $label, mixed $required, mixed $actual, string $result, ?string $message): array
    {
        return compact('criterion', 'label', 'required', 'actual', 'result', 'message');
    }
}
