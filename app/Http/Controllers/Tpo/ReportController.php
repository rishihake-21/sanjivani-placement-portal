<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Controller;
use App\Support\CsvSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** University-wide CSV exports optionally filtered to a department and batch. */
class ReportController extends Controller
{
    public function students(Request $request): StreamedResponse
    {
        [$department, $year] = $this->filters($request);

        $query = DB::table('students as s')
            ->join('departments as d', 'd.id', '=', 's.department_id')
            ->join('programs as pr', 'pr.id', '=', 'd.program_id')
            ->leftJoin('divisions as dv', 'dv.id', '=', 's.division_id')
            ->leftJoin('branches as b', 'b.id', '=', 's.branch_id')
            ->leftJoin('placements as p', fn ($join) => $join->on('p.student_id', '=', 's.id')->where('p.status', '=', 'PLACED'))
            ->leftJoin('companies as c', 'c.id', '=', 'p.company_id')
            ->when($department, fn ($query) => $query->where('s.department_id', $department))
            ->when($year, fn ($query) => $query->where('s.graduation_year', $year))
            ->orderBy('pr.code')->orderBy('d.code')->orderByRaw('COALESCE(dv.code, b.code)')->orderBy('s.university_id')
            ->select(
                's.university_id',
                's.full_name',
                'pr.code as program_code',
                'd.code as department_code',
                DB::raw("COALESCE(dv.code, b.code, 'UNASSIGNED') as division_code"),
                's.current_semester',
                's.graduation_year',
                's.admission_type',
                's.opted_out_of_placement',
                'p.id as placement_id',
                'c.name as company',
                'p.role_title',
                'p.ctc_lpa'
            );

        return $this->csv('placement-students.csv',
            ['University ID', 'Name', 'Programme code', 'Department code', 'Division code', 'Semester', 'Graduation year', 'Admission type', 'Placement status', 'Company', 'Role', 'CTC (LPA)'],
            $query,
            fn ($row) => [
                $row->university_id,
                $row->full_name,
                $row->program_code,
                $row->department_code,
                $row->division_code,
                $row->current_semester,
                $row->graduation_year,
                $row->admission_type,
                $row->placement_id ? 'PLACED' : ($row->opted_out_of_placement ? 'OPTED_OUT' : 'UNPLACED'),
                $row->company,
                $row->role_title,
                $row->ctc_lpa,
            ]);
    }

    public function placements(Request $request): StreamedResponse
    {
        [$department, $year] = $this->filters($request);

        $query = DB::table('placements as p')
            ->join('students as s', 's.id', '=', 'p.student_id')
            ->join('departments as d', 'd.id', '=', 's.department_id')
            ->join('programs as pr', 'pr.id', '=', 'd.program_id')
            ->leftJoin('divisions as dv', 'dv.id', '=', 's.division_id')
            ->leftJoin('branches as b', 'b.id', '=', 's.branch_id')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->when($department, fn ($query) => $query->where('s.department_id', $department))
            ->when($year, fn ($query) => $query->where('s.graduation_year', $year))
            ->orderBy('pr.code')->orderBy('d.code')->orderByRaw('COALESCE(dv.code, b.code)')->orderBy('c.name')->orderBy('s.university_id')
            ->select(
                's.university_id',
                's.full_name',
                'pr.code as program_code',
                'd.code as department_code',
                DB::raw("COALESCE(dv.code, b.code, 'UNASSIGNED') as division_code"),
                'c.name as company',
                'p.role_title',
                'p.ctc_lpa',
                'p.location',
                'p.offer_date',
                'p.joining_date',
                'p.status'
            );

        return $this->csv('placements.csv',
            ['University ID', 'Name', 'Programme code', 'Department code', 'Division code', 'Company', 'Role', 'CTC (LPA)', 'Location', 'Offer date', 'Joining date', 'Status'],
            $query,
            fn ($row) => [
                $row->university_id,
                $row->full_name,
                $row->program_code,
                $row->department_code,
                $row->division_code,
                $row->company,
                $row->role_title,
                $row->ctc_lpa,
                $row->location,
                $row->offer_date,
                $row->joining_date,
                $row->status,
            ]);
    }

    private function filters(Request $request): array
    {
        $request->validate([
            'department_id' => ['sometimes', 'integer'],
            'graduation_year' => ['sometimes', 'integer', 'between:2000,2100'],
        ]);

        return [
            $request->filled('department_id') ? $request->integer('department_id') : null,
            $request->filled('graduation_year') ? $request->integer('graduation_year') : null,
        ];
    }

    private function csv(string $filename, array $header, $query, callable $mapRow): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $query, $mapRow) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $header);
            foreach ($query->cursor() as $row) {
                fputcsv($output, CsvSafe::row($mapRow($row)));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
