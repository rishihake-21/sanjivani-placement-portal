<?php

namespace App\Http\Controllers\Recruiter;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Recruiter\ApplicantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** READ-ONLY student cards for the applicants of the recruiter's company. */
class ApplicantController extends Controller
{
    public function __construct(private ApplicantService $applicants)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->applicants->index($request, $request->attributes->get('recruiter')));
    }

    /** Opening a full card is recorded: it exposes a student's verified records and contact details. */
    public function show(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $recruiter = $request->attributes->get('recruiter');
        $application = $this->applicants->find($recruiter, $id);
        $card = $this->applicants->detail($application);

        $audit->record('recruiter.applicant_viewed', $application, $application->student_id, null, null, $request->user(), [
            'company_id' => $recruiter->company_id,
            'contact_visible' => $card['student']['contact']['visible'],
        ]);

        return response()->json(['data' => $card]);
    }
}
