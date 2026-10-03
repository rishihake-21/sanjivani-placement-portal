<?php

namespace App\Http\Controllers\Tpo;

use App\Enums\ApplicationStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStageRequest;
use App\Http\Requests\PlacementDetailsRequest;
use App\Http\Requests\StageChangeRequest;
use App\Models\Application;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Services\ApplicationService;
use App\Services\PlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationService $applications, private PlacementService $placements)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'drive_id' => ['sometimes', 'integer'],
            'company_id' => ['sometimes', 'integer'],
            'stage' => ['sometimes', Rule::enum(ApplicationStage::class)],
            'department_id' => ['sometimes', 'integer'],
            'branch_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $applications = Application::query()
            ->with(['student:id,university_id,full_name,branch_id,department_id', 'student.branch:id,code', 'drive:id,title,company_id', 'drive.company:id,name'])
            ->when($request->filled('drive_id'), fn ($q) => $q->where('drive_id', $request->integer('drive_id')))
            ->when($request->filled('company_id'), fn ($q) => $q->whereHas('drive', fn ($d) => $d->where('company_id', $request->integer('company_id'))))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')->toString()))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . addcslashes($request->string('search')->toString(), '%_\\') . '%';
                $q->whereHas('student', fn ($s) => $s->where('full_name', 'ilike', $term)->orWhere('university_id', 'ilike', $term));
            })
            ->orderByDesc('stage_updated_at')->paginate(25);

        return response()->json($applications->through(fn (Application $a) => $this->row($a)));
    }

    public function show(int $id): JsonResponse
    {
        $application = Application::query()->with(['student.branch:id,code,name', 'drive.company', 'history.changedBy:id,name'])->findOrFail($id);
        $placement = Placement::query()->where('application_id', $application->id)->first();

        return response()->json(['data' => $this->row($application) + [
            'data_snapshot' => $application->data_snapshot,
            'placement' => $placement ? ['id' => $placement->id, 'status' => $placement->status->value, 'role_title' => $placement->role_title, 'ctc_lpa' => $placement->ctc_lpa] : null,
            'history' => $application->history->map(fn ($h) => [
                'from' => $h->from_stage, 'to' => $h->to_stage, 'by' => $h->changedBy?->name, 'remarks' => $h->remarks, 'at' => $h->created_at,
            ])->values(),
        ]]);
    }

    public function changeStage(StageChangeRequest $request, int $id): JsonResponse
    {
        $application = $this->applications->changeStage(
            $request->user(), Application::query()->findOrFail($id), ApplicationStage::from($request->validated('stage')),
            $request->validated('remarks'), (bool) $request->validated('force', false),
        );

        return response()->json(['data' => $this->row($application->load('student.branch:id,code', 'drive.company'))]);
    }

    public function bulkChangeStage(BulkStageRequest $request, int $driveId): JsonResponse
    {
        $drive = PlacementDrive::query()->findOrFail($driveId);

        $count = $this->applications->bulkChangeStage(
            $request->user(), $drive, $request->validated('application_ids'), ApplicationStage::from($request->validated('stage')),
            $request->validated('remarks'), (bool) $request->validated('force', false),
        );

        return response()->json(['updated' => $count]);
    }

    public function recordOffer(PlacementDetailsRequest $request, int $id): JsonResponse
    {
        $placement = $this->placements->recordOffer($request->user(), Application::query()->findOrFail($id), $request->validated());

        return response()->json(['data' => PlacementController::row($placement)], 201);
    }

    private function row(Application $a): array
    {
        return [
            'id' => $a->id,
            'student' => [
                'id' => $a->student->id, 'university_id' => $a->student->university_id,
                'full_name' => $a->student->full_name, 'branch' => $a->student->branch?->code,
            ],
            'company' => $a->drive->company?->name,
            'drive' => ['id' => $a->drive->id, 'title' => $a->drive->title],
            'stage' => $a->stage->value,
            'applied_at' => $a->applied_at,
            'stage_updated_at' => $a->stage_updated_at,
        ];
    }
}
