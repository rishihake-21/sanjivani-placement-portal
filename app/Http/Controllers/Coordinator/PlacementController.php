<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\Coordinator\PlacementDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** READ-ONLY. Official placement records are maintained and verified by the TPO. */
class PlacementController extends Controller
{
    public function __construct(private PlacementDirectoryService $directory)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->directory->index($request));
    }
}
