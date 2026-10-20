<?php

namespace App\Http\Controllers\Student;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadStandaloneDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Services\DocumentService;
use App\Services\Student\StudentDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Standalone documents only (resume, other certificates). Marksheets/certificates go through their record. */
class DocumentController extends Controller
{
    public function __construct(private StudentDocumentService $documents)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DocumentResource::collection(
                $this->documents->documents($this->currentStudent($request), $request->boolean('history'))
            ),
        ]);
    }

    public function store(UploadStandaloneDocumentRequest $request, DocumentService $documents): JsonResponse
    {
        $document = $documents->uploadStandalone(
            $this->currentStudent($request),
            $request->file('document'),
            DocumentType::from($request->validated('document_type')),
            $request->user(),
        );

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }
}
