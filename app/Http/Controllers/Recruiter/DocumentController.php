<?php

namespace App\Http\Controllers\Recruiter;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Opens a student's photo, resume, marksheet or certificate for a recruiter. DocumentPolicy decides:
 * the file must be APPROVED and its student must have a live application to this recruiter's company.
 * Inline by default (so a photo can sit in an <img>); ?download=1 saves it instead.
 */
class DocumentController extends Controller
{
    public function show(Request $request, Document $document, DocumentService $documents, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('view', $document);

        $download = $request->boolean('download');
        $audit->record('document.viewed', $document, $document->student_id, null, null, $request->user(), [
            'company_id' => $request->attributes->get('recruiter')->company_id,
            'download' => $download,
        ]);

        return $download ? $documents->download($document) : $documents->view($document);
    }
}
