<?php

namespace App\Services\Coordinator;

use App\Models\Placement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/** READ-ONLY read model for the department's official placement records (maintained by the TPO). */
class PlacementDirectoryService
{
    public function index(Request $request): LengthAwarePaginator
    {
        $request->validate([
            'status' => ['sometimes', 'in:OFFERED,PLACED,DECLINED'],
            'company_id' => ['sometimes', 'integer'],
            'division_id' => ['sometimes', 'integer'],
            'branch_id' => ['sometimes', 'integer'],
            'graduation_year' => ['sometimes', 'integer'],
        ]);

        return Placement::query()->forDepartment((int) $request->user()->department_id)
            ->with(['student:id,university_id,full_name,branch_id,division_id,graduation_year', 'student.branch:id,code', 'student.division:id,code,name', 'company:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('division_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('division_id', $request->integer('division_id'))))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('graduation_year'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('graduation_year', $request->integer('graduation_year'))))
            ->orderByDesc('offer_date')->orderByDesc('id')
            ->paginate(25)
            ->through(fn (Placement $p) => [
                'id' => $p->id,
                'student' => ['id' => $p->student->id, 'university_id' => $p->student->university_id, 'full_name' => $p->student->full_name, 'division' => $p->student->division?->code ?? $p->student->branch?->code, 'branch' => $p->student->division?->code ?? $p->student->branch?->code],
                'company' => $p->company->name,
                'role_title' => $p->role_title,
                'ctc_lpa' => $p->ctc_lpa,
                'location' => $p->location,
                'offer_date' => $p->offer_date?->toDateString(),
                'joining_date' => $p->joining_date?->toDateString(),
                'status' => $p->status->value,
            ]);
    }
}
