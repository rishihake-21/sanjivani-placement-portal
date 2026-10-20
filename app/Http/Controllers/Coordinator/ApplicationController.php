<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\Coordinator\ApplicationDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** READ-ONLY monitoring of the department's applications and recruitment progress. */
class ApplicationController extends Controller
{
    public function __construct(private ApplicationDirectoryService $directory)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->directory->index($request));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->directory->show($request, $id)]);
    }

    /** One student's placement progress: every application with its stage trail, plus official placement records. */
    public function studentProgress(Request $request, int $studentId): JsonResponse
    {
        return response()->json(['data' => $this->directory->studentProgress($request, $studentId)]);
    }
}
