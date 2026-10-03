<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** READ-ONLY. Official placement records are maintained and verified by the TPO. */
class PlacementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'in:OFFERED,PLACED,DECLINED'],
            'company_id' => ['sometimes', 'integer'],
            'branch_id' => ['sometimes', 'integer'],
            'graduation_year' => ['sometimes', 'integer'],
        ]);

        $placements = Placement::query()->forDepartment((int) $request->user()->department_id)
            ->with(['student:id,university_id,full_name,branch_id,graduation_year', 'student.branch:id,code', 'company:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('graduation_year'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('graduation_year', $request->integer('graduation_year'))))
            ->orderByDesc('offer_date')->orderByDesc('id')
            ->paginate(25);

        return response()->json($placements->through(fn (Placement $p) => [
            'id' => $p->id,
            'student' => ['id' => $p->student->id, 'university_id' => $p->student->university_id, 'full_name' => $p->student->full_name, 'branch' => $p->student->branch?->code],
            'company' => $p->company->name,
            'role_title' => $p->role_title,
            'ctc_lpa' => $p->ctc_lpa,
            'location' => $p->location,
            'offer_date' => $p->offer_date?->toDateString(),
            'joining_date' => $p->joining_date?->toDateString(),
            'status' => $p->status->value,
        ]));
    }
}
