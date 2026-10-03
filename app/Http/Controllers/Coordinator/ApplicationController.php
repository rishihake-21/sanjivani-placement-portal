<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\ApplicationStage;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Placement;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** READ-ONLY monitoring of the department's applications and recruitment progress. */
class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $departmentId = (int) $request->user()->department_id;
        $request->validate([
            'drive_id' => ['sometimes', 'integer'],
            'stage' => ['sometimes', Rule::enum(ApplicationStage::class)],
            'branch_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $applications = Application::query()->forDepartment($departmentId)
            ->with(['student:id,university_id,full_name,branch_id,current_semester', 'student.branch:id,code', 'drive:id,title,company_id', 'drive.company:id,name'])
            ->when($request->filled('drive_id'), fn ($q) => $q->where('drive_id', $request->integer('drive_id')))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')->toString()))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . addcslashes($request->string('search')->toString(), '%_\\') . '%';
                $q->whereHas('student', fn ($s) => $s->where('full_name', 'ilike', $term)->orWhere('university_id', 'ilike', $term));
            })
            ->orderByDesc('stage_updated_at')
            ->paginate(25);

        return response()->json($applications->through(fn (Application $a) => $this->row($a)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $application = Application::query()->forDepartment((int) $request->user()->department_id)
            ->with(['student.branch:id,code,name', 'drive.company', 'history.changedBy:id,name'])
            ->findOrFail($id);

        return response()->json(['data' => $this->row($application) + [
            // verified academics frozen at the moment the student applied
            'data_snapshot' => $application->data_snapshot,
            'history' => $application->history->map(fn ($h) => [
                'from' => $h->from_stage,
                'to' => $h->to_stage,
                'by' => $h->changedBy?->name,
                'remarks' => $h->remarks,
                'at' => $h->created_at,
            ])->values(),
        ]]);
    }

    /** One student's placement progress: every application with its stage trail, plus official placement records. */
    public function studentProgress(Request $request, int $studentId): JsonResponse
    {
        $student = Student::query()->where('department_id', (int) $request->user()->department_id)
            ->with('branch:id,code')->findOrFail($studentId);

        $applications = Application::query()->where('student_id', $student->id)
            ->with(['drive.company', 'history.changedBy:id,name'])->orderByDesc('applied_at')->get();

        $placements = Placement::query()->where('student_id', $student->id)->with('company:id,name')->orderByDesc('offer_date')->get();

        return response()->json(['data' => [
            'student' => ['id' => $student->id, 'university_id' => $student->university_id, 'full_name' => $student->full_name, 'branch' => $student->branch->code],
            'is_placed' => $placements->contains(fn ($p) => $p->status->value === 'PLACED'),
            'applications' => $applications->map(fn (Application $a) => [
                'id' => $a->id,
                'company' => $a->drive->company->name,
                'drive_title' => $a->drive->title,
                'stage' => $a->stage->value,
                'applied_at' => $a->applied_at,
                'stage_updated_at' => $a->stage_updated_at,
                'history' => $a->history->map(fn ($h) => ['from' => $h->from_stage, 'to' => $h->to_stage, 'by' => $h->changedBy?->name, 'remarks' => $h->remarks, 'at' => $h->created_at])->values(),
            ])->values(),
            'placements' => $placements->map(fn ($p) => [
                'company' => $p->company->name, 'role_title' => $p->role_title, 'ctc_lpa' => $p->ctc_lpa,
                'location' => $p->location, 'offer_date' => $p->offer_date?->toDateString(),
                'joining_date' => $p->joining_date?->toDateString(), 'status' => $p->status->value,
            ])->values(),
        ]]);
    }

    private function row(Application $a): array
    {
        return [
            'id' => $a->id,
            'student' => [
                'id' => $a->student->id,
                'university_id' => $a->student->university_id,
                'full_name' => $a->student->full_name,
                'branch' => $a->student->branch?->code,
            ],
            'company' => $a->drive->company?->name,
            'drive' => ['id' => $a->drive->id, 'title' => $a->drive->title],
            'stage' => $a->stage->value,
            'applied_at' => $a->applied_at,
            'stage_updated_at' => $a->stage_updated_at,
        ];
    }
}
