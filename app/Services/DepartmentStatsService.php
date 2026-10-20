<?php

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\DocumentType;
use App\Enums\DriveStatus;
use App\Enums\PlacementStatus;
use App\Models\PlacementDrive;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Department-level numbers for the coordinator dashboard and reports.
 * Everything is scoped by students.department_id; callers pass the coordinator's own department.
 *
 * "Placed" = a placement row with status PLACED (official, TPO-verified), counted once per student.
 * Placement % = placed / (students - students who opted out of placement).
 */
class DepartmentStatsService
{
    public function dashboard(int $departmentId, ?int $graduationYear = null): array
    {
        $students = fn (): Builder => DB::table('students as s')
            ->where('s.department_id', $departmentId)
            ->when($graduationYear, fn (Builder $q) => $q->where('s.graduation_year', $graduationYear));

        $total = $students()->count();
        $optedOut = $students()->where('s.opted_out_of_placement', true)->count();
        $lateral = $students()->where('s.admission_type', 'LATERAL')->count();

        $bySemester = $students()->selectRaw('s.current_semester as semester, count(*) as n')
            ->groupBy('s.current_semester')->orderBy('semester')->pluck('n', 'semester');

        $applicationsByStage = $students()->join('applications as a', 'a.student_id', '=', 's.id')
            ->selectRaw('a.stage, count(*) as n')->groupBy('a.stage')->pluck('n', 'stage');
        $studentsApplied = $students()->join('applications as a', 'a.student_id', '=', 's.id')
            ->distinct()->count('s.id');

        $placed = $students()->join('placements as p', 'p.student_id', '=', 's.id')
            ->where('p.status', PlacementStatus::PLACED->value)->distinct()->count('s.id');
        $offers = $students()->join('placements as p', 'p.student_id', '=', 's.id')
            ->where('p.status', PlacementStatus::OFFERED->value)->count();

        $denominator = $total - $optedOut;

        $stageCount = fn (array $values) => (int) collect($values)->sum(fn ($v) => $applicationsByStage[$v] ?? 0);

        return [
            'students' => [
                'total' => $total,
                'lateral_entry' => $lateral,
                'opted_out_of_placement' => $optedOut,
                'by_semester' => $bySemester,
            ],
            'verification_queue' => [
                'academic_records' => $students()->join('academic_records as r', 'r.student_id', '=', 's.id')->where('r.status', 'PENDING')->count(),
                'experiences' => $students()->join('experiences as e', 'e.student_id', '=', 's.id')->where('e.status', 'PENDING')->count(),
                'documents' => $students()->join('documents as d', 'd.student_id', '=', 's.id')
                    ->where('d.status', 'PENDING')->whereIn('d.document_type', DocumentType::standaloneValues())->count(),
                'open_reupload_requests' => $students()->join('reupload_requests as q', 'q.student_id', '=', 's.id')->where('q.status', 'OPEN')->count(),
            ],
            'applications' => [
                'total' => (int) $applicationsByStage->sum(),
                'students_applied' => $studentsApplied,
                'by_stage' => $applicationsByStage,
                'in_recruitment' => $stageCount(ApplicationStage::recruitmentValues()),
                'selected' => $stageCount(ApplicationStage::selectedValues()),
            ],
            'placements' => [
                'placed_students' => $placed,
                'open_offers' => $offers,
                'unplaced_students' => max(0, $denominator - $placed),
                'placement_percentage' => $denominator > 0 ? round($placed / $denominator * 100, 2) : null,
            ],
            'active_drives' => PlacementDrive::query()->visibleToDepartment($departmentId)
                ->where('status', DriveStatus::PUBLISHED->value)->count(),
        ];
    }

    /** One row per division of the department (legacy rows without division_id are reported by their branch alias). */
    public function divisionSummary(int $departmentId, ?int $graduationYear = null): array
    {
        $yearFilter = fn (Builder $query) => $query->when(
            $graduationYear,
            fn (Builder $filtered) => $filtered->where('s.graduation_year', $graduationYear)
        );
        $groupKey = "CASE WHEN s.division_id IS NOT NULL THEN 'D:' || s.division_id::text ELSE 'B:' || COALESCE(s.branch_id::text, '0') END";

        $divisions = $yearFilter(
            DB::table('students as s')
                ->leftJoin('divisions as dv', 'dv.id', '=', 's.division_id')
                ->leftJoin('branches as b', 'b.id', '=', 's.branch_id')
                ->where('s.department_id', $departmentId)
        )
            ->selectRaw($groupKey . ' as group_key')
            ->selectRaw('s.division_id, s.branch_id, COALESCE(dv.code, b.code, \'UNASSIGNED\') as code, COALESCE(dv.name, b.name, \'Unassigned\') as name')
            ->selectRaw('count(*) as students, sum(case when s.opted_out_of_placement then 1 else 0 end) as opted_out')
            ->groupByRaw($groupKey . ', s.division_id, s.branch_id, COALESCE(dv.code, b.code, \'UNASSIGNED\'), COALESCE(dv.name, b.name, \'Unassigned\')')
            ->orderBy('code')
            ->get();

        $applied = $yearFilter(
            DB::table('applications as a')
                ->join('students as s', 's.id', '=', 'a.student_id')
                ->where('s.department_id', $departmentId)
        )
            ->selectRaw($groupKey . ' as group_key, count(distinct s.id) as n')
            ->groupByRaw($groupKey)
            ->pluck('n', 'group_key');

        $placed = $yearFilter(
            DB::table('placements as p')
                ->join('students as s', 's.id', '=', 'p.student_id')
                ->where('s.department_id', $departmentId)
                ->where('p.status', PlacementStatus::PLACED->value)
        )
            ->selectRaw($groupKey . ' as group_key, count(distinct s.id) as placed, max(p.ctc_lpa) as highest_ctc, avg(p.ctc_lpa) as average_ctc')
            ->groupByRaw($groupKey)
            ->get()
            ->keyBy('group_key');

        return $divisions->map(function ($row) use ($applied, $placed) {
            $p = $placed->get($row->group_key);
            $eligible = (int) $row->students - (int) $row->opted_out;
            $placedCount = (int) ($p->placed ?? 0);

            return [
                'division_id' => $row->division_id === null ? null : (int) $row->division_id,
                // Compatibility for clients that still call the grouping a branch.
                'branch_id' => $row->branch_id === null ? null : (int) $row->branch_id,
                'code' => $row->code,
                'name' => $row->name,
                'students' => (int) $row->students,
                'opted_out' => (int) $row->opted_out,
                'applied' => (int) ($applied[$row->group_key] ?? 0),
                'placed' => $placedCount,
                'unplaced' => max(0, $eligible - $placedCount),
                'placement_percentage' => $eligible > 0 ? round($placedCount / $eligible * 100, 2) : null,
                'highest_ctc_lpa' => $p?->highest_ctc !== null ? (float) $p->highest_ctc : null,
                'average_ctc_lpa' => $p?->average_ctc !== null ? round((float) $p->average_ctc, 2) : null,
            ];
        })->values()->all();
    }

    /** Backward-compatible method name used by older callers. */
    public function branchSummary(int $departmentId, ?int $graduationYear = null): array
    {
        return $this->divisionSummary($departmentId, $graduationYear);
    }

}
