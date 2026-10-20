<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Coordinator;
use App\Models\Department;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentBranchController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->with(['branches' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $departments->map(fn (Department $department) => $this->departmentRow($department))]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('departments', 'code')],
        ]);

        try {
            $department = DB::transaction(function () use ($request, $data) {
                $department = Department::query()->create($data + ['is_active' => true]);
                $this->audit->record('department.created', $department, null, null, AuditLogger::snapshot($department), $request->user());

                return $department->load('branches');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A department with that name or code already exists.');
        }

        return response()->json(['data' => $this->departmentRow($department)], 201);
    }

    public function updateDepartment(Request $request, int $department): JsonResponse
    {
        $model = Department::query()->findOrFail($department);
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->ignore($model->id)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('departments', 'code')->ignore($model->id)],
        ]);

        try {
            $model = DB::transaction(function () use ($request, $model, $data) {
                $model = Department::query()->whereKey($model->id)->lockForUpdate()->firstOrFail();
                $old = AuditLogger::snapshot($model);
                $model->fill($data);
                $model->save();

                if ($old !== AuditLogger::snapshot($model)) {
                    $this->audit->record('department.updated', $model, null, $old, AuditLogger::snapshot($model), $request->user());
                }

                return $model->load('branches');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A department with that name or code already exists.');
        }

        return response()->json(['data' => $this->departmentRow($model)]);
    }

    public function deactivateDepartment(Request $request, int $department): JsonResponse
    {
        $model = DB::transaction(function () use ($request, $department) {
            $model = Department::query()->whereKey($department)->lockForUpdate()->firstOrFail();
            abort_unless($model->is_active, 409, 'This department is already inactive.');
            abort_if(
                $model->branches()->where('is_active', true)->exists(),
                409,
                'Deactivate all active branches in this department first.'
            );
            abort_if(
                Coordinator::query()->where('department_id', $model->id)->where('is_active', true)->exists(),
                409,
                'Deactivate the active coordinator for this department first.'
            );

            $old = AuditLogger::snapshot($model);
            $model->forceFill(['is_active' => false])->save();
            $this->audit->record('department.deactivated', $model, null, $old, AuditLogger::snapshot($model), $request->user());

            return $model->load('branches');
        });

        return response()->json(['data' => $this->departmentRow($model)]);
    }

    public function reactivateDepartment(Request $request, int $department): JsonResponse
    {
        $model = DB::transaction(function () use ($request, $department) {
            $model = Department::query()->whereKey($department)->lockForUpdate()->firstOrFail();
            abort_if($model->is_active, 409, 'This department is already active.');

            $old = AuditLogger::snapshot($model);
            $model->forceFill(['is_active' => true])->save();
            $this->audit->record('department.reactivated', $model, null, $old, AuditLogger::snapshot($model), $request->user());

            return $model->load('branches');
        });

        return response()->json(['data' => $this->departmentRow($model)]);
    }

    public function storeBranch(Request $request, int $department): JsonResponse
    {
        $departmentModel = Department::query()->findOrFail($department);
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->where('department_id', $departmentModel->id)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('branches', 'code')],
        ]);

        try {
            $branch = DB::transaction(function () use ($request, $departmentModel, $data) {
                $parent = Department::query()->whereKey($departmentModel->id)->lockForUpdate()->firstOrFail();
                abort_unless($parent->is_active, 409, 'Activate the department before adding a branch.');

                $branch = $parent->branches()->create($data + ['is_active' => true]);
                $this->audit->record('branch.created', $branch, null, null, AuditLogger::snapshot($branch), $request->user());

                return $branch->load('department');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A branch with that name in this department or that code already exists.');
        }

        return response()->json(['data' => $this->branchRow($branch)], 201);
    }

    public function updateBranch(Request $request, int $branch): JsonResponse
    {
        $model = Branch::query()->with('department')->findOrFail($branch);
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->where('department_id', $model->department_id)->ignore($model->id)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('branches', 'code')->ignore($model->id)],
        ]);

        try {
            $model = DB::transaction(function () use ($request, $model, $data) {
                $model = Branch::query()->whereKey($model->id)->lockForUpdate()->firstOrFail();
                $old = AuditLogger::snapshot($model);
                $model->fill($data);
                $model->save();

                if ($old !== AuditLogger::snapshot($model)) {
                    $this->audit->record('branch.updated', $model, null, $old, AuditLogger::snapshot($model), $request->user());
                }

                return $model->load('department');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A branch with that name in this department or that code already exists.');
        }

        return response()->json(['data' => $this->branchRow($model)]);
    }

    public function deactivateBranch(Request $request, int $branch): JsonResponse
    {
        $model = $this->setBranchStatus($request, $branch, false);

        return response()->json(['data' => $this->branchRow($model)]);
    }

    public function reactivateBranch(Request $request, int $branch): JsonResponse
    {
        $model = $this->setBranchStatus($request, $branch, true);

        return response()->json(['data' => $this->branchRow($model)]);
    }

    private function setBranchStatus(Request $request, int $id, bool $active): Branch
    {
        return DB::transaction(function () use ($request, $id, $active) {
            $model = Branch::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($model->is_active === $active, 409, $active ? 'This branch is already active.' : 'This branch is already inactive.');

            $department = Department::query()->whereKey($model->department_id)->lockForUpdate()->firstOrFail();
            if ($active) {
                abort_unless($department->is_active, 409, 'Activate the parent department before reactivating this branch.');
            }

            $old = AuditLogger::snapshot($model);
            $model->forceFill(['is_active' => $active])->save();
            $this->audit->record(
                $active ? 'branch.reactivated' : 'branch.deactivated',
                $model,
                null,
                $old,
                AuditLogger::snapshot($model),
                $request->user()
            );

            return $model->load('department');
        });
    }

    private function normalizeFields(Request $request): void
    {
        $request->merge([
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
            'code' => is_string($request->input('code')) ? strtoupper(trim($request->input('code'))) : $request->input('code'),
        ]);
    }

    private function departmentRow(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'is_active' => (bool) $department->is_active,
            'active_branches_count' => $department->branches->where('is_active', true)->count(),
            'branches' => $department->branches->map(fn (Branch $branch) => $this->branchRow($branch, $department))->values(),
        ];
    }

    private function branchRow(Branch $branch, ?Department $parent = null): array
    {
        $department = $parent ?? ($branch->relationLoaded('department') ? $branch->getRelation('department') : null);

        return [
            'id' => $branch->id,
            'department_id' => $branch->department_id,
            'department' => $department
                ? ['id' => $department->id, 'name' => $department->name, 'code' => $department->code, 'is_active' => (bool) $department->is_active]
                : null,
            'name' => $branch->name,
            'code' => $branch->code,
            'is_active' => (bool) $branch->is_active,
        ];
    }
}
