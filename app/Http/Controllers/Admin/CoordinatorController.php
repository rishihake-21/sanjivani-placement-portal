<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateCoordinatorRequest;
use App\Http\Requests\StoreCoordinatorRequest;
use App\Http\Requests\UpdateCoordinatorRequest;
use App\Models\Coordinator;
use App\Services\CoordinatorAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** System Admin manages coordinator accounts. No student data is reachable from here. */
class CoordinatorController extends Controller
{
    public function __construct(private CoordinatorAccountService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['department_id' => ['sometimes', 'integer'], 'active' => ['sometimes', 'boolean']]);

        $coordinators = Coordinator::query()->with(['user:id,name,email', 'department:id,name,code'])
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('department_id')->orderByDesc('is_active')->get();

        return response()->json(['data' => $coordinators->map(fn ($c) => $this->row($c))]);
    }

    public function store(StoreCoordinatorRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->row($this->service->create($request->user(), $request->validated()))], 201);
    }

    public function update(UpdateCoordinatorRequest $request, int $coordinator): JsonResponse
    {
        $model = Coordinator::query()->findOrFail($coordinator);

        return response()->json(['data' => $this->row($this->service->update($request->user(), $model, $request->validated()))]);
    }

    public function deactivate(DeactivateCoordinatorRequest $request, int $coordinator): JsonResponse
    {
        $model = Coordinator::query()->findOrFail($coordinator);

        return response()->json(['data' => $this->row($this->service->deactivate($request->user(), $model, $request->validated('reason')))]);
    }

    public function reactivate(Request $request, int $coordinator): JsonResponse
    {
        $model = Coordinator::query()->findOrFail($coordinator);

        return response()->json(['data' => $this->row($this->service->reactivate($request->user(), $model))]);
    }

    private function row(Coordinator $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->user->name,
            'email' => $c->user->email,
            'department' => $c->department->only(['id', 'name', 'code']),
            'employee_id' => $c->employee_id,
            'phone' => $c->phone,
            'designation' => $c->designation,
            'is_active' => $c->is_active,
            'active_from' => $c->active_from?->toDateString(),
            'active_until' => $c->active_until?->toDateString(),
        ];
    }
}
