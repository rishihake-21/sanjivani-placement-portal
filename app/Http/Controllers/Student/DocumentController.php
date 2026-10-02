<?php

namespace App\Http\Controllers\Student;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadStandaloneDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Standalone documents only (resume, other certificates). Marksheets/certificates go through their record. */
class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->currentStudent($request)->documents()->orderByDesc('created_at');

        if (! $request->boolean('history')) {
            $query->where('status', '<>', 'SUPERSEDED');
        }

        return response()->json(['data' => DocumentResource::collection($query->get())]);
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
