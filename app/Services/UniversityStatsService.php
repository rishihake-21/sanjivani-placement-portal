<?php

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\DriveStatus;
use App\Models\PlacementDrive;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * University-wide numbers for the TPO. Same definitions as the coordinator's department stats:
 *   placed            = distinct students with a PLACED (TPO-verified) placement
 *   placement %       = placed / (students - students who opted out)
 * All methods accept an optional graduation year (the placement batch).
 */
class UniversityStatsService
{
    public function dashboard(?int $year = null): array
    {
        $students = fn (): Builder => DB::table('students as s')->when($year, fn (Builder $q) => $q->where('s.graduation_year', $year));

        $total = $students()->count();
        $optedOut = $students()->where('s.opted_out_of_placement', true)->count();
        $placed = $students()->join('placements as p', 'p.student_id', '=', 's.id')->where('p.status', 'PLACED')->distinct()->count('s.id');
        $denominator = $total - $optedOut;

        $byStage = $students()->join('applications as a', 'a.student_id', '=', 's.id')
            ->selectRaw('a.stage, count(*) as n')->groupBy('a.stage')->pluck('n', 'stage');
        $stageCount = fn (array $values) => (int) collect($values)->sum(fn ($v) => $byStage[$v] ?? 0);

        $ctc = $students()->join('placements as p', 'p.student_id', '=', 's.id')->where('p.status', 'PLACED')
            ->selectRaw('max(p.ctc_lpa) as highest, avg(p.ctc_lpa) as average')->first();

        $drivesByStatus = PlacementDrive::query()->when($year, fn ($q) => $q->where('graduation_year', $year))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'students' => ['total' => $total, 'opted_out_of_placement' => $optedOut, 'lateral_entry' => $students()->where('s.admission_type', 'LATERAL')->count()],
            'drives' => [
                'by_status' => $drivesByStatus,
                'closing_in_7_days' => PlacementDrive::query()->with('company:id,name')
                    ->where('status', DriveStatus::PUBLISHED->value)
                    ->whereBetween('application_deadline', [now(), now()->addDays(7)])
                    ->when($year, fn ($q) => $q->where('graduation_year', $year))
                    ->orderBy('application_deadline')->limit(10)->get()
                    ->map(fn ($d) => ['id' => $d->id, 'company' => $d->company->name, 'title' => $d->title, 'application_deadline' => $d->application_deadline])->values(),
            ],
            'applications' => [
                'total' => (int) $byStage->sum(),
                'by_stage' => $byStage,
                'in_recruitment' => $stageCount(ApplicationStage::recruitmentValues()),
                'selected' => $stageCount(ApplicationStage::selectedValues()),
                'students_applied' => $students()->join('applications as a', 'a.student_id', '=', 's.id')->distinct()->count('s.id'),
            ],
            'placements' => [
                'placed_students' => $placed,
                'open_offers' => $students()->join('placements as p', 'p.student_id', '=', 's.id')->where('p.status', 'OFFERED')->count(),
                'unplaced_students' => max(0, $denominator - $placed),
                'placement_percentage' => $denominator > 0 ? round($placed / $denominator * 100, 2) : null,
                'highest_ctc_lpa' => $ctc?->highest !== null ? (float) $ctc->highest : null,
                'average_ctc_lpa' => $ctc?->average !== null ? round((float) $ctc->average, 2) : null,
            ],
            'verification_backlog' => [
                'academic_records' => DB::table('academic_records')->where('status', 'PENDING')->count(),
                'experiences' => DB::table('experiences')->where('status', 'PENDING')->count(),
            ],
        ];
    }

    /** One row per department or per division. The legacy 'branch' level is accepted as a division alias. */
    public function summaryBy(string $level, ?int $year = null): array
    {
        $isDivision = in_array($level, ['branch', 'division'], true);
        $groupColumn = $isDivision
            ? "CASE WHEN s.division_id IS NOT NULL THEN 'D:' || s.division_id::text ELSE 'B:' || COALESCE(s.branch_id::text, '0') END"
            : 's.department_id';
        $year = $year ?: null;
        $yearFilter = fn (Builder $query) => $query->when($year, fn (Builder $filtered) => $filtered->where('s.graduation_year', $year));

        $base = DB::table('students as s')
            ->join('departments as d', 'd.id', '=', 's.department_id');

        if ($isDivision) {
            $base->leftJoin('divisions as dv', 'dv.id', '=', 's.division_id')
                ->leftJoin('branches as b', 'b.id', '=', 's.branch_id');
        }

        if ($isDivision) {
            $rows = $yearFilter($base)
                ->selectRaw($groupColumn . ' as group_key')
                ->selectRaw('s.division_id, s.branch_id, COALESCE(dv.code, b.code, \'UNASSIGNED\') as code, COALESCE(dv.name, b.name, \'Unassigned\') as name, d.code as department')
                ->selectRaw('count(*) as students, sum(case when s.opted_out_of_placement then 1 else 0 end) as opted_out')
                ->groupByRaw($groupColumn . ', s.division_id, s.branch_id, COALESCE(dv.code, b.code, \'UNASSIGNED\'), COALESCE(dv.name, b.name, \'Unassigned\'), d.code')
                ->orderBy('code')->get();
        } else {
            $rows = $yearFilter($base)
                ->selectRaw('d.id as id, d.code as code, d.name as name, count(*) as students, sum(case when s.opted_out_of_placement then 1 else 0 end) as opted_out')
                ->groupBy('d.id', 'd.code', 'd.name')
                ->orderBy('code')->get();
        }

        $applied = $yearFilter(DB::table('applications as a')->join('students as s', 's.id', '=', 'a.student_id'))
            ->selectRaw($groupColumn . ' as gid, count(distinct s.id) as n')
            ->groupByRaw($groupColumn)->pluck('n', 'gid');

        $placed = $yearFilter(DB::table('placements as p')->join('students as s', 's.id', '=', 'p.student_id')->where('p.status', 'PLACED'))
            ->selectRaw($groupColumn . ' as gid, count(distinct s.id) as placed, max(p.ctc_lpa) as highest, avg(p.ctc_lpa) as average')
            ->groupByRaw($groupColumn)->get()->keyBy('gid');

        return $rows->map(function ($row) use ($applied, $placed, $isDivision) {
            $key = $isDivision ? $row->group_key : $row->id;
            $placement = $placed->get($key);
            $eligible = (int) $row->students - (int) $row->opted_out;
            $placedCount = (int) ($placement->placed ?? 0);
            $divisionId = $isDivision && $row->division_id !== null ? (int) $row->division_id : null;
            $branchId = $isDivision && $row->branch_id !== null ? (int) $row->branch_id : null;

            return array_filter([
                'id' => $isDivision ? ($divisionId ?? $branchId ?? 0) : (int) $row->id,
                'division_id' => $divisionId,
                // Compatibility field for older reports clients.
                'branch_id' => $branchId,
                'code' => $row->code,
                'name' => $row->name,
                'department' => $isDivision ? $row->department : null,
                'students' => (int) $row->students,
                'opted_out' => (int) $row->opted_out,
                'applied' => (int) ($applied[$key] ?? 0),
                'placed' => $placedCount,
                'unplaced' => max(0, $eligible - $placedCount),
                'placement_percentage' => $eligible > 0 ? round($placedCount / $eligible * 100, 2) : null,
                'highest_ctc_lpa' => $placement?->highest !== null ? (float) $placement->highest : null,
                'average_ctc_lpa' => $placement?->average !== null ? round((float) $placement->average, 2) : null,
            ], fn ($value, $field) => ! in_array($field, ['division_id', 'branch_id', 'department'], true) || $value !== null, ARRAY_FILTER_USE_BOTH);
        })->values()->all();
    }

    /** One row per company: drives, applicants, students selected, students placed, CTC. */
    public function companySummary(?int $year = null): array
    {
        $selected = "'" . implode("','", ApplicationStage::selectedValues()) . "'";

        $rows = DB::table('companies as c')
            ->join('placement_drives as d', 'd.company_id', '=', 'c.id')
            ->leftJoin('applications as a', 'a.drive_id', '=', 'd.id')
            ->whereIn('d.status', [DriveStatus::PUBLISHED->value, DriveStatus::CLOSED->value])
            ->when($year, fn (Builder $q) => $q->where('d.graduation_year', $year))
            ->selectRaw("c.id, c.name, count(distinct d.id) as drives, count(a.id) as applications,
                count(distinct case when a.stage in ({$selected}) then a.student_id end) as selected")
            ->groupBy('c.id', 'c.name')->orderBy('c.name')->get();

        $placed = DB::table('placements as p')->join('students as s', 's.id', '=', 'p.student_id')
            ->where('p.status', 'PLACED')
            ->when($year, fn (Builder $q) => $q->where('s.graduation_year', $year))
            ->selectRaw('p.company_id, count(distinct s.id) as placed, max(p.ctc_lpa) as highest, avg(p.ctc_lpa) as average')
            ->groupBy('p.company_id')->get()->keyBy('company_id');

        return $rows->map(fn ($r) => [
            'company_id' => (int) $r->id,
            'company' => $r->name,
            'drives' => (int) $r->drives,
            'applications' => (int) $r->applications,
            'students_selected' => (int) $r->selected,
            'students_placed' => (int) ($placed->get($r->id)->placed ?? 0),
            'highest_ctc_lpa' => ($h = $placed->get($r->id)?->highest) !== null ? (float) $h : null,
            'average_ctc_lpa' => ($a = $placed->get($r->id)?->average) !== null ? round((float) $a, 2) : null,
        ])->values()->all();
    }
}
