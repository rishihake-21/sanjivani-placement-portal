<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Program;
use App\Models\Coordinator;
use App\Models\Department;
use App\Models\StudentMasterList;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * System admin dashboard. This page intentionally reports only administration and roster
 * metadata; student academic records, experience documents and placement details stay outside
 * the system-admin data scope.
 */
class AdminPageController extends Controller
{
    public function dashboard(): View
    {
        $departments = Department::query()
            ->with('program:id,name,code,is_active')
            ->withCount(['divisions as active_divisions_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'program_id', 'is_active']);

        $metrics = [
            'departments_total' => Department::query()->count(),
            'departments_active' => Department::query()->where('is_active', true)->count(),
            'divisions_active' => Division::query()->where('is_active', true)->count(),
            'programs_total' => Program::query()->count(),
            'programs_active' => Program::query()->where('is_active', true)->count(),
            'coordinators_active' => Coordinator::query()->where('is_active', true)->count(),
            'coordinators_inactive' => Coordinator::query()->where('is_active', false)->count(),
            'roster_total' => StudentMasterList::query()->count(),
            'roster_unclaimed' => StudentMasterList::query()->whereNull('claimed_at')->count(),
            'roster_claimed' => StudentMasterList::query()->whereNotNull('claimed_at')->count(),
        ];

        $recentBatches = DB::table('student_master_list')
            ->select('import_batch')
            ->selectRaw('COUNT(*) AS entries')
            ->selectRaw('MAX(updated_at) AS last_updated')
            ->whereNotNull('import_batch')
            ->groupBy('import_batch')
            ->orderByDesc('last_updated')
            ->limit(5)
            ->get()
            ->map(fn ($batch) => [
                'name' => $batch->import_batch,
                'entries' => (int) $batch->entries,
                'last_updated' => $batch->last_updated,
            ])
            ->values();

        return view('admin.dashboard', [
            'departments' => $departments,
            'departmentRows' => $departments
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'is_active' => (bool) $department->is_active,
                    'program_id' => (int) $department->program_id,
                    'program_name' => $department->program?->name,
                    'program_code' => $department->program?->code,
                    'active_divisions_count' => (int) $department->active_divisions_count,
                ])
                ->values(),
            'departmentOptions' => $departments
                ->where('is_active', true)
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'program_id' => (int) $department->program_id,
                    'program_name' => $department->program?->name,
                    'program_code' => $department->program?->code,
                    'active_divisions_count' => (int) $department->active_divisions_count,
                ])
                ->values(),
            'metrics' => $metrics,
            'recentBatches' => $recentBatches,
        ]);
    }
}
