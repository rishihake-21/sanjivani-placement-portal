<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\DepartmentStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DepartmentStatsService $stats)
    {
    }

    /** GET /coordinator/dashboard?graduation_year=2029 */
    public function dashboard(Request $request): JsonResponse
    {
        $year = $this->year($request);

        return response()->json([
            'graduation_year' => $year,
            'data' => $this->stats->dashboard((int) $request->user()->department_id, $year),
        ]);
    }

    /** GET /coordinator/reports/branch-summary?graduation_year=2029 */
    public function branchSummary(Request $request): JsonResponse
    {
        $year = $this->year($request);

        return response()->json([
            'graduation_year' => $year,
            'data' => $this->stats->branchSummary((int) $request->user()->department_id, $year),
        ]);
    }

    private function year(Request $request): ?int
    {
        $request->validate(['graduation_year' => ['sometimes', 'integer', 'between:2000,2100']]);

        return $request->filled('graduation_year') ? $request->integer('graduation_year') : null;
    }
}
