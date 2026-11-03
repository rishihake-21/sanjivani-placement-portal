<?php

namespace App\Http\Controllers\Recruiter;

use App\Http\Controllers\Controller;
use App\Services\Recruiter\ApplicantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The company's own drives (published or closed) with a head-count of applicants per stage. */
class DriveController extends Controller
{
    public function __construct(private ApplicantService $applicants)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->applicants->drives($request->attributes->get('recruiter'))]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->applicants->drive($request->attributes->get('recruiter'), $id)]);
    }
}
