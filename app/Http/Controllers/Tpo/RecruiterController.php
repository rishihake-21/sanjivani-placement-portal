<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateCoordinatorRequest;
use App\Http\Requests\StoreRecruiterRequest;
use App\Http\Requests\UpdateRecruiterRequest;
use App\Models\Recruiter;
use App\Services\RecruiterAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The TPO creates and switches off the recruiter logins of the companies that recruit on campus. */
class RecruiterController extends Controller
{
    public function __construct(private RecruiterAccountService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['company_id' => ['sometimes', 'integer'], 'active' => ['sometimes', 'boolean']]);

        $recruiters = Recruiter::query()->with(['user:id,name,email', 'company:id,name'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('company_id')->orderByDesc('is_active')->orderBy('id')
            ->paginate(25)
            ->through(fn (Recruiter $r) => $this->row($r));

        return response()->json($recruiters);
    }

    public function store(StoreRecruiterRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->row($this->service->create($request->user(), $request->validated()))], 201);
    }

    public function update(UpdateRecruiterRequest $request, int $recruiter): JsonResponse
    {
        $model = Recruiter::query()->findOrFail($recruiter);

        return response()->json(['data' => $this->row($this->service->update($request->user(), $model, $request->validated()))]);
    }

    public function deactivate(DeactivateCoordinatorRequest $request, int $recruiter): JsonResponse
    {
        $model = Recruiter::query()->findOrFail($recruiter);

        return response()->json(['data' => $this->row($this->service->deactivate($request->user(), $model, $request->validated('reason')))]);
    }

    public function reactivate(Request $request, int $recruiter): JsonResponse
    {
        $model = Recruiter::query()->findOrFail($recruiter);

        return response()->json(['data' => $this->row($this->service->reactivate($request->user(), $model))]);
    }

    private function row(Recruiter $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->user->name,
            'email' => $r->user->email,
            'company' => ['id' => $r->company->id, 'name' => $r->company->name],
            'designation' => $r->designation,
            'phone' => $r->phone,
            'is_active' => $r->is_active,
            'active_from' => $r->active_from?->toDateString(),
            'active_until' => $r->active_until?->toDateString(),
        ];
    }
}
