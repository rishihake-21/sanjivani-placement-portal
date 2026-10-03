<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelDriveRequest;
use App\Http\Requests\StoreDriveRequest;
use App\Http\Requests\UpdateDriveRequest;
use App\Models\PlacementDrive;
use App\Services\DriveService;
use App\Support\DrivePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriveController extends Controller
{
    public function __construct(private DriveService $drives)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'in:DRAFT,PUBLISHED,CLOSED,CANCELLED'],
            'company_id' => ['sometimes', 'integer'],
            'graduation_year' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $drives = PlacementDrive::query()->with('company:id,name')
            ->withCount('applications')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('graduation_year'), fn ($q) => $q->where('graduation_year', $request->integer('graduation_year')))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'ilike', '%' . addcslashes($request->string('search')->toString(), '%_\\') . '%'))
            ->orderByDesc('created_at')->paginate(20);

        return response()->json($drives->through(fn (PlacementDrive $d) => DrivePresenter::summary($d) + ['applications_count' => $d->applications_count]));
    }

    public function store(StoreDriveRequest $request): JsonResponse
    {
        return response()->json(['data' => DrivePresenter::detail($this->drives->create($request->user(), $request->validated()))], 201);
    }

    public function show(int $id): JsonResponse
    {
        $drive = PlacementDrive::query()->with(['company', 'eligibility', 'branches'])->findOrFail($id);

        $byStage = $drive->applications()->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');

        return response()->json(['data' => DrivePresenter::detail($drive) + ['applications_by_stage' => $byStage]]);
    }

    public function update(UpdateDriveRequest $request, int $id): JsonResponse
    {
        $drive = PlacementDrive::query()->findOrFail($id);

        return response()->json(['data' => DrivePresenter::detail($this->drives->update($request->user(), $drive, $request->validated()))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->drives->delete($request->user(), PlacementDrive::query()->findOrFail($id));

        return response()->json(null, 204);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $drive = $this->drives->publish($request->user(), PlacementDrive::query()->findOrFail($id));

        return response()->json(['data' => DrivePresenter::detail($drive)]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $drive = $this->drives->close($request->user(), PlacementDrive::query()->findOrFail($id));

        return response()->json(['data' => DrivePresenter::detail($drive)]);
    }

    public function cancel(CancelDriveRequest $request, int $id): JsonResponse
    {
        $drive = $this->drives->cancel($request->user(), PlacementDrive::query()->findOrFail($id), $request->validated('reason'));

        return response()->json(['data' => DrivePresenter::detail($drive)]);
    }
}
