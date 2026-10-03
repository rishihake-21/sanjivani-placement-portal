<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['sometimes', 'string', 'max:100'], 'active' => ['sometimes', 'boolean']]);

        $companies = Company::query()->withCount('drives')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'ilike', '%' . addcslashes($request->string('search')->toString(), '%_\\') . '%'))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')->paginate(25);

        return response()->json($companies);
    }

    public function store(StoreCompanyRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->assertNameFree($request->validated('name'));

        $company = Company::create($request->validated() + ['created_by' => $request->user()->id, 'is_active' => true]);
        $audit->record('company.created', $company, null, null, AuditLogger::snapshot($company), $request->user());

        return response()->json(['data' => $company], 201);
    }

    public function show(int $id): JsonResponse
    {
        $company = Company::query()->withCount('drives')->findOrFail($id);

        return response()->json(['data' => $company]);
    }

    public function update(UpdateCompanyRequest $request, int $id, AuditLogger $audit): JsonResponse
    {
        $company = Company::query()->findOrFail($id);
        if ($request->has('name')) {
            $this->assertNameFree($request->validated('name'), $company->id);
        }

        $old = array_intersect_key($company->getAttributes(), $request->validated());
        $company->fill($request->validated())->save();
        $audit->record('company.updated', $company, null, $old, $request->validated(), $request->user());

        return response()->json(['data' => $company->fresh()]);
    }

    /** Company names are unique case-insensitively (DB index too); this gives a clean 422 instead of a 500. */
    private function assertNameFree(string $name, ?int $ignoreId = null): void
    {
        $exists = Company::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => ['A company with this name already exists.']]);
        }
    }
}
