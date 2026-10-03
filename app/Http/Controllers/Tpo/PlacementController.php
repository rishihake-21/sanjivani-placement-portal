<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManualPlacementRequest;
use App\Http\Requests\PlacementDetailsRequest;
use App\Http\Requests\RemarksRequest;
use App\Models\Placement;
use App\Services\PlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Official placement records (offer -> verified placement). Only the TPO writes these. */
class PlacementController extends Controller
{
    public function __construct(private PlacementService $placements)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'in:OFFERED,PLACED,DECLINED'],
            'company_id' => ['sometimes', 'integer'],
            'department_id' => ['sometimes', 'integer'],
            'branch_id' => ['sometimes', 'integer'],
            'graduation_year' => ['sometimes', 'integer'],
        ]);

        $placements = Placement::query()->with(['student:id,university_id,full_name,branch_id', 'student.branch:id,code', 'company:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('graduation_year'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('graduation_year', $request->integer('graduation_year'))))
            ->orderByDesc('offer_date')->orderByDesc('id')->paginate(25);

        return response()->json($placements->through(fn (Placement $p) => self::row($p)));
    }

    public function store(ManualPlacementRequest $request): JsonResponse
    {
        return response()->json(['data' => self::row($this->placements->createManual($request->user(), $request->validated()))], 201);
    }

    public function update(PlacementDetailsRequest $request, int $id): JsonResponse
    {
        $placement = $this->placements->update($request->user(), Placement::query()->findOrFail($id), $request->validated());

        return response()->json(['data' => self::row($placement)]);
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => self::row($this->placements->verify($request->user(), Placement::query()->findOrFail($id)))]);
    }

    public function decline(RemarksRequest $request, int $id): JsonResponse
    {
        $placement = $this->placements->decline($request->user(), Placement::query()->findOrFail($id), $request->validated('remarks'));

        return response()->json(['data' => self::row($placement)]);
    }

    public static function row(Placement $p): array
    {
        $p->loadMissing(['student.branch:id,code', 'company:id,name']);

        return [
            'id' => $p->id,
            'application_id' => $p->application_id,
            'student' => $p->student ? ['id' => $p->student->id, 'university_id' => $p->student->university_id, 'full_name' => $p->student->full_name, 'branch' => $p->student->branch?->code] : null,
            'company' => $p->company?->name,
            'role_title' => $p->role_title,
            'ctc_lpa' => $p->ctc_lpa,
            'location' => $p->location,
            'offer_date' => $p->offer_date?->toDateString(),
            'joining_date' => $p->joining_date?->toDateString(),
            'status' => $p->status->value,
            'verified_at' => $p->verified_at,
        ];
    }
}
