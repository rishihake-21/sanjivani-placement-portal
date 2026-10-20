<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
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
            ->withCount(['branches as active_branches_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);

        $metrics = [
            'departments_total' => Department::query()->count(),
            'departments_active' => Department::query()->where('is_active', true)->count(),
            'branches_active' => Branch::query()->where('is_active', true)->count(),
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
                    'active_branches_count' => (int) $department->active_branches_count,
                ])
                ->values(),
            'departmentOptions' => $departments
                ->where('is_active', true)
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'active_branches_count' => (int) $department->active_branches_count,
                ])
                ->values(),
            'metrics' => $metrics,
            'recentBatches' => $recentBatches,
        ]);
    }
}
