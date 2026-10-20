<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\Coordinator\DriveDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * READ-ONLY. Drives are created and published by the TPO; a coordinator sees published/closed
 * drives that target at least one branch of their own department, with THEIR department's applicants.
 */
class DriveController extends Controller
{
    public function __construct(private DriveDirectoryService $directory)
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
}
