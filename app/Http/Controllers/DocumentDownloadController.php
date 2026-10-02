<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The ONLY way to get at a stored file. Access follows the student-data rules:
 * owner, same-department coordinator, TPO (and the future read-only HOD). System admins are refused.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, Document $document, DocumentService $documents, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('view', $document);

        $user = $request->user();
        if (in_array($user->role->value, config('tpms.audit_document_views_for'), true)) {
            $audit->record('document.viewed', $document, $document->student_id, null, null, $user);
        }

        return $documents->download($document);
    }
}
