<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Controller;
use App\Services\UniversityStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private UniversityStatsService $stats)
    {
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json(['graduation_year' => $this->year($request), 'data' => $this->stats->dashboard($this->year($request))]);
    }

    public function departments(Request $request): JsonResponse
    {
        return response()->json(['graduation_year' => $this->year($request), 'data' => $this->stats->summaryBy('department', $this->year($request))]);
    }

    public function branches(Request $request): JsonResponse
    {
        return response()->json(['graduation_year' => $this->year($request), 'data' => $this->stats->summaryBy('branch', $this->year($request))]);
    }

    public function companies(Request $request): JsonResponse
    {
        return response()->json(['graduation_year' => $this->year($request), 'data' => $this->stats->companySummary($this->year($request))]);
    }

    private function year(Request $request): ?int
    {
        $request->validate(['graduation_year' => ['sometimes', 'integer', 'between:2000,2100']]);

        return $request->filled('graduation_year') ? $request->integer('graduation_year') : null;
    }
}
