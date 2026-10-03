<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Support\CsvSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV exports of the coordinator's OWN department. Cells are sanitised against formula injection. */
class ReportController extends Controller
{
    /** One row per student with official placement status. GET /coordinator/reports/students.csv */
    public function students(Request $request): StreamedResponse
    {
        $departmentId = (int) $request->user()->department_id;
        $year = $this->year($request);

        $query = DB::table('students as s')
            ->join('branches as b', 'b.id', '=', 's.branch_id')
            ->leftJoin('placements as p', function ($join) {
                $join->on('p.student_id', '=', 's.id')->where('p.status', '=', 'PLACED');
            })
            ->leftJoin('companies as c', 'c.id', '=', 'p.company_id')
            ->where('s.department_id', $departmentId)
            ->when($year, fn ($q) => $q->where('s.graduation_year', $year))
            ->orderBy('b.code')->orderBy('s.university_id')
            ->select('s.university_id', 's.full_name', 'b.code as branch', 's.current_semester', 's.graduation_year',
                's.admission_type', 's.opted_out_of_placement', 'p.id as placement_id', 'c.name as company', 'p.role_title', 'p.ctc_lpa');

        return $this->csv('department-students.csv',
            ['University ID', 'Name', 'Branch', 'Semester', 'Graduation year', 'Admission type', 'Placement status', 'Company', 'Role', 'CTC (LPA)'],
            $query,
            fn ($r) => [
                $r->university_id, $r->full_name, $r->branch, $r->current_semester, $r->graduation_year, $r->admission_type,
                $r->placement_id ? 'PLACED' : ($r->opted_out_of_placement ? 'OPTED_OUT' : 'UNPLACED'),
                $r->company, $r->role_title, $r->ctc_lpa,
            ]);
    }

    /** One row per placement record. GET /coordinator/reports/placements.csv */
    public function placements(Request $request): StreamedResponse
    {
        $departmentId = (int) $request->user()->department_id;
        $year = $this->year($request);

        $query = DB::table('placements as p')
            ->join('students as s', 's.id', '=', 'p.student_id')
            ->join('branches as b', 'b.id', '=', 's.branch_id')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->where('s.department_id', $departmentId)
            ->when($year, fn ($q) => $q->where('s.graduation_year', $year))
            ->orderBy('c.name')->orderBy('s.university_id')
            ->select('s.university_id', 's.full_name', 'b.code as branch', 'c.name as company', 'p.role_title',
                'p.ctc_lpa', 'p.location', 'p.offer_date', 'p.joining_date', 'p.status');

        return $this->csv('department-placements.csv',
            ['University ID', 'Name', 'Branch', 'Company', 'Role', 'CTC (LPA)', 'Location', 'Offer date', 'Joining date', 'Status'],
            $query,
            fn ($r) => [$r->university_id, $r->full_name, $r->branch, $r->company, $r->role_title, $r->ctc_lpa, $r->location, $r->offer_date, $r->joining_date, $r->status]);
    }

    private function csv(string $filename, array $header, $query, callable $mapRow): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $query, $mapRow) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($query->cursor() as $row) {
                fputcsv($out, CsvSafe::row($mapRow($row)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function year(Request $request): ?int
    {
        $request->validate(['graduation_year' => ['sometimes', 'integer', 'between:2000,2100']]);

        return $request->filled('graduation_year') ? $request->integer('graduation_year') : null;
    }
}
