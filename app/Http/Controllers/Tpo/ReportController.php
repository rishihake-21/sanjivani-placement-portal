<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Controller;
use App\Support\CsvSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** University-wide CSV exports (optionally ?department_id= & ?graduation_year=). Cells are formula-injection safe. */
class ReportController extends Controller
{
    public function students(Request $request): StreamedResponse
    {
        [$department, $year] = $this->filters($request);

        $query = DB::table('students as s')
            ->join('departments as d', 'd.id', '=', 's.department_id')
            ->join('branches as b', 'b.id', '=', 's.branch_id')
            ->leftJoin('placements as p', fn ($j) => $j->on('p.student_id', '=', 's.id')->where('p.status', '=', 'PLACED'))
            ->leftJoin('companies as c', 'c.id', '=', 'p.company_id')
            ->when($department, fn ($q) => $q->where('s.department_id', $department))
            ->when($year, fn ($q) => $q->where('s.graduation_year', $year))
            ->orderBy('d.code')->orderBy('b.code')->orderBy('s.university_id')
            ->select('s.university_id', 's.full_name', 'd.code as department', 'b.code as branch', 's.current_semester',
                's.graduation_year', 's.admission_type', 's.opted_out_of_placement', 'p.id as placement_id', 'c.name as company', 'p.role_title', 'p.ctc_lpa');

        return $this->csv('placement-students.csv',
            ['University ID', 'Name', 'Department', 'Branch', 'Semester', 'Graduation year', 'Admission type', 'Placement status', 'Company', 'Role', 'CTC (LPA)'],
            $query,
            fn ($r) => [$r->university_id, $r->full_name, $r->department, $r->branch, $r->current_semester, $r->graduation_year, $r->admission_type,
                $r->placement_id ? 'PLACED' : ($r->opted_out_of_placement ? 'OPTED_OUT' : 'UNPLACED'), $r->company, $r->role_title, $r->ctc_lpa]);
    }

    public function placements(Request $request): StreamedResponse
    {
        [$department, $year] = $this->filters($request);

        $query = DB::table('placements as p')
            ->join('students as s', 's.id', '=', 'p.student_id')
            ->join('departments as d', 'd.id', '=', 's.department_id')
            ->join('branches as b', 'b.id', '=', 's.branch_id')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->when($department, fn ($q) => $q->where('s.department_id', $department))
            ->when($year, fn ($q) => $q->where('s.graduation_year', $year))
            ->orderBy('c.name')->orderBy('s.university_id')
            ->select('s.university_id', 's.full_name', 'd.code as department', 'b.code as branch', 'c.name as company',
                'p.role_title', 'p.ctc_lpa', 'p.location', 'p.offer_date', 'p.joining_date', 'p.status');

        return $this->csv('placements.csv',
            ['University ID', 'Name', 'Department', 'Branch', 'Company', 'Role', 'CTC (LPA)', 'Location', 'Offer date', 'Joining date', 'Status'],
            $query,
            fn ($r) => [$r->university_id, $r->full_name, $r->department, $r->branch, $r->company, $r->role_title, $r->ctc_lpa, $r->location, $r->offer_date, $r->joining_date, $r->status]);
    }

    private function filters(Request $request): array
    {
        $request->validate(['department_id' => ['sometimes', 'integer'], 'graduation_year' => ['sometimes', 'integer', 'between:2000,2100']]);

        return [
            $request->filled('department_id') ? $request->integer('department_id') : null,
            $request->filled('graduation_year') ? $request->integer('graduation_year') : null,
        ];
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
}
