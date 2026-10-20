<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\Department;
use App\Models\Division;
use App\Models\Program;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * System Admin manages the academic structure: Programme -> Department -> Division.
 * Coordinators remain assigned to departments, never to individual divisions.
 */
class DepartmentBranchController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(): JsonResponse
    {
        $programs = Program::query()
            ->withCount(['departments as active_departments_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')->get();

        $departments = Department::query()
            ->with('program:id,name,code,is_active')
            ->withCount(['divisions as active_divisions_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')->get();

        $divisions = Division::query()
            ->with(['department.program'])
            ->orderBy('name')->get();

        return response()->json(['data' => [
            'programs' => $programs->map(fn (Program $program) => $this->programRow($program))->values(),
            'departments' => $departments->map(fn (Department $department) => $this->departmentRow($department))->values(),
            'divisions' => $divisions->map(fn (Division $division) => $this->divisionRow($division))->values(),
        ]]);
    }

    public function storeProgram(Request $request): JsonResponse
    {
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('programs', 'name')],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('programs', 'code')],
        ]);

        try {
            $program = DB::transaction(function () use ($request, $data) {
                $program = Program::query()->create($data + ['is_active' => true]);
                $this->audit->record('program.created', $program, null, null, AuditLogger::snapshot($program), $request->user());

                return $program;
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A programme with that name or code already exists.');
        }

        return response()->json(['data' => $this->programRow($program)], 201);
    }

    public function updateProgram(Request $request, int $program): JsonResponse
    {
        $model = Program::query()->findOrFail($program);
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('programs', 'name')->ignore($model->id)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('programs', 'code')->ignore($model->id)],
        ]);

        try {
            $model = DB::transaction(function () use ($request, $model, $data) {
                $model = Program::query()->whereKey($model->id)->lockForUpdate()->firstOrFail();
                $old = AuditLogger::snapshot($model);
                $model->fill($data)->save();
                if ($old !== AuditLogger::snapshot($model)) {
                    $this->audit->record('program.updated', $model, null, $old, AuditLogger::snapshot($model), $request->user());
                }

                return $model->loadCount(['departments as active_departments_count' => fn ($q) => $q->where('is_active', true)]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A programme with that name or code already exists.');
        }

        return response()->json(['data' => $this->programRow($model)]);
    }

    public function deactivateProgram(Request $request, int $program): JsonResponse
    {
        $model = DB::transaction(function () use ($request, $program) {
            $model = Program::query()->whereKey($program)->lockForUpdate()->firstOrFail();
            abort_unless($model->is_active, 409, 'This programme is already inactive.');
            abort_if($model->departments()->where('is_active', true)->exists(), 409, 'Deactivate active departments in this programme first.');

            $old = AuditLogger::snapshot($model);
            $model->forceFill(['is_active' => false])->save();
            $this->audit->record('program.deactivated', $model, null, $old, AuditLogger::snapshot($model), $request->user());

            return $model->loadCount(['departments as active_departments_count' => fn ($q) => $q->where('is_active', true)]);
        });

        return response()->json(['data' => $this->programRow($model)]);
    }

    public function reactivateProgram(Request $request, int $program): JsonResponse
    {
        $model = DB::transaction(function () use ($request, $program) {
            $model = Program::query()->whereKey($program)->lockForUpdate()->firstOrFail();
            abort_if($model->is_active, 409, 'This programme is already active.');

            $old = AuditLogger::snapshot($model);
            $model->forceFill(['is_active' => true])->save();
            $this->audit->record('program.reactivated', $model, null, $old, AuditLogger::snapshot($model), $request->user());

            return $model->loadCount(['departments as active_departments_count' => fn ($q) => $q->where('is_active', true)]);
        });

        return response()->json(['data' => $this->programRow($model)]);
    }

    public function storeDepartment(Request $request, int $program): JsonResponse
    {
        $parent = Program::query()->findOrFail($program);
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->where('program_id', $parent->id)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('departments', 'code')->where('program_id', $parent->id)],
        ]);

        try {
            $department = DB::transaction(function () use ($request, $parent, $data) {
                $program = Program::query()->whereKey($parent->id)->lockForUpdate()->firstOrFail();
                abort_unless($program->is_active, 409, 'Activate the programme before adding a department.');

                $department = Department::query()->create($data + ['program_id' => $program->id, 'is_active' => true]);
                $this->audit->record('department.created', $department, null, null, AuditLogger::snapshot($department), $request->user());

                return $department->load('program')->loadCount(['divisions as active_divisions_count' => fn ($q) => $q->where('is_active', true)]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A department with that name or code already exists in this programme.');
        }

        return response()->json(['data' => $this->departmentRow($department)], 201);
    }

    public function updateDepartment(Request $request, int $department): JsonResponse
    {
        $model = Department::query()->findOrFail($department);
        $targetProgramId = $request->filled('program_id') ? (int) $request->input('program_id') : (int) $model->program_id;
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->where('program_id', $targetProgramId)->ignore($model->id)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('departments', 'code')->where('program_id', $targetProgramId)->ignore($model->id)],
            'program_id' => ['sometimes', 'integer', Rule::exists('programs', 'id')->where('is_active', true)],
        ]);

        try {
            $model = DB::transaction(function () use ($request, $model, $data) {
                $model = Department::query()->whereKey($model->id)->lockForUpdate()->firstOrFail();
                if (isset($data['program_id'])) {
                    $newProgram = Program::query()->whereKey($data['program_id'])->lockForUpdate()->firstOrFail();
                    abort_unless($newProgram->is_active, 409, 'Choose an active programme.');
                }

                $old = AuditLogger::snapshot($model);
                $model->fill($data)->save();
                if ($old !== AuditLogger::snapshot($model)) {
                    $this->audit->record('department.updated', $model, null, $old, AuditLogger::snapshot($model), $request->user());
                }

                return $model->load('program')->loadCount(['divisions as active_divisions_count' => fn ($q) => $q->where('is_active', true)]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A department with that name or code already exists in this programme.');
        }

        return response()->json(['data' => $this->departmentRow($model)]);
    }

    public function deactivateDepartment(Request $request, int $department): JsonResponse
    {
        $model = DB::transaction(function () use ($request, $department) {
            $model = Department::query()->whereKey($department)->lockForUpdate()->firstOrFail();
            abort_unless($model->is_active, 409, 'This department is already inactive.');
            abort_if($model->divisions()->where('is_active', true)->exists(), 409, 'Deactivate active divisions in this department first.');
            abort_if(Coordinator::query()->where('department_id', $model->id)->where('is_active', true)->exists(), 409, 'Deactivate the active coordinator for this department first.');

            $old = AuditLogger::snapshot($model);
            $model->forceFill(['is_active' => false])->save();
            $this->audit->record('department.deactivated', $model, null, $old, AuditLogger::snapshot($model), $request->user());

            return $model->load('program')->loadCount(['divisions as active_divisions_count' => fn ($q) => $q->where('is_active', true)]);
        });

        return response()->json(['data' => $this->departmentRow($model)]);
    }

    public function reactivateDepartment(Request $request, int $department): JsonResponse
    {
        $model = DB::transaction(function () use ($request, $department) {
            $model = Department::query()->whereKey($department)->lockForUpdate()->firstOrFail();
            abort_if($model->is_active, 409, 'This department is already active.');
            $program = Program::query()->whereKey($model->program_id)->lockForUpdate()->firstOrFail();
            abort_unless($program->is_active, 409, 'Reactivate the programme before this department.');

            $old = AuditLogger::snapshot($model);
            $model->forceFill(['is_active' => true])->save();
            $this->audit->record('department.reactivated', $model, null, $old, AuditLogger::snapshot($model), $request->user());

            return $model->load('program')->loadCount(['divisions as active_divisions_count' => fn ($q) => $q->where('is_active', true)]);
        });

        return response()->json(['data' => $this->departmentRow($model)]);
    }

    public function storeDivision(Request $request, int $department): JsonResponse
    {
        $parent = Department::query()->with('program')->findOrFail($department);
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('divisions', 'name')->where('department_id', $parent->id)],
            'code' => ['required', 'string', 'max:30', 'alpha_dash:ascii', Rule::unique('divisions', 'code')->where('department_id', $parent->id)],
        ]);

        try {
            $division = DB::transaction(function () use ($request, $parent, $data) {
                $department = Department::query()->with('program')->whereKey($parent->id)->lockForUpdate()->firstOrFail();
                abort_unless($department->is_active && $department->program?->is_active, 409, 'Activate the programme and department before adding a division.');

                $division = $department->divisions()->create($data + ['is_active' => true]);
                $this->audit->record('division.created', $division, null, null, AuditLogger::snapshot($division), $request->user());

                return $division->load('department.program');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A division with that name or code already exists in this department.');
        }

        return response()->json(['data' => $this->divisionRow($division)], 201);
    }

    public function updateDivision(Request $request, int $division): JsonResponse
    {
        $model = Division::query()->with('department.program')->findOrFail($division);
        $this->normalizeFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('divisions', 'name')->where('department_id', $model->department_id)->ignore($model->id)],
            'code' => ['required', 'string', 'max:30', 'alpha_dash:ascii', Rule::unique('divisions', 'code')->where('department_id', $model->department_id)->ignore($model->id)],
        ]);

        try {
            $model = DB::transaction(function () use ($request, $model, $data) {
                $model = Division::query()->whereKey($model->id)->lockForUpdate()->firstOrFail();
                $old = AuditLogger::snapshot($model);
                $model->fill($data)->save();
                if ($old !== AuditLogger::snapshot($model)) {
                    $this->audit->record('division.updated', $model, null, $old, AuditLogger::snapshot($model), $request->user());
                }

                return $model->load('department.program');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A division with that name in this department or that code already exists.');
        }

        return response()->json(['data' => $this->divisionRow($model)]);
    }

    public function deactivateDivision(Request $request, int $division): JsonResponse
    {
        return $this->setDivisionStatus($request, $division, false);
    }

    public function reactivateDivision(Request $request, int $division): JsonResponse
    {
        return $this->setDivisionStatus($request, $division, true);
    }

    private function setDivisionStatus(Request $request, int $id, bool $active): JsonResponse
    {
        $model = DB::transaction(function () use ($request, $id, $active) {
            $model = Division::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($model->is_active === $active, 409, $active ? 'This division is already active.' : 'This division is already inactive.');
            $department = Department::query()->with('program')->whereKey($model->department_id)->lockForUpdate()->firstOrFail();
            if ($active) {
                abort_unless($department->is_active && $department->program?->is_active, 409, 'Reactivate the programme and department before this division.');
            }

            $old = AuditLogger::snapshot($model);
            $model->forceFill(['is_active' => $active])->save();
            $this->audit->record($active ? 'division.reactivated' : 'division.deactivated', $model, null, $old, AuditLogger::snapshot($model), $request->user());

            return $model->load('department.program');
        });

        return response()->json(['data' => $this->divisionRow($model)]);
    }

    private function normalizeFields(Request $request): void
    {
        $request->merge([
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
            'code' => is_string($request->input('code')) ? strtoupper(trim($request->input('code'))) : $request->input('code'),
        ]);
    }

    private function programRow(Program $program): array
    {
        return [
            'id' => $program->id,
            'name' => $program->name,
            'code' => $program->code,
            'is_active' => (bool) $program->is_active,
            'active_departments_count' => (int) ($program->active_departments_count ?? $program->departments()->where('is_active', true)->count()),
        ];
    }

    private function departmentRow(Department $department): array
    {
        return [
            'id' => $department->id,
            'program_id' => $department->program_id,
            'program' => $department->relationLoaded('program') && $department->program
                ? ['id' => $department->program->id, 'name' => $department->program->name, 'code' => $department->program->code, 'is_active' => (bool) $department->program->is_active]
                : null,
            'name' => $department->name,
            'code' => $department->code,
            'is_active' => (bool) $department->is_active,
            'active_divisions_count' => (int) ($department->active_divisions_count ?? $department->divisions()->where('is_active', true)->count()),
        ];
    }

    private function divisionRow(Division $division): array
    {
        $department = $division->relationLoaded('department') ? $division->getRelation('department') : null;
        $program = $department && $department->relationLoaded('program') ? $department->getRelation('program') : null;

        return [
            'id' => $division->id,
            'department_id' => $division->department_id,
            'department' => $department ? [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'is_active' => (bool) $department->is_active,
                'program' => $program ? ['id' => $program->id, 'name' => $program->name, 'code' => $program->code, 'is_active' => (bool) $program->is_active] : null,
            ] : null,
            'name' => $division->name,
            'code' => $division->code,
            'is_active' => (bool) $division->is_active,
        ];
    }
}
