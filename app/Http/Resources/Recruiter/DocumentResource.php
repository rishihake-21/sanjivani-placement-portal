<?php

namespace App\Http\Resources\Recruiter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a recruiter learns about a file: its kind, name and where to open it. No storage path, hash,
 * reviewer or rejection details. view_url streams inline (images, PDFs); download_url forces a save.
 */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'document_type' => $this->document_type->value,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'verified_at' => $this->reviewed_at,
            'view_url' => route('recruiter.documents.show', ['document' => $this->uuid]),
            'download_url' => route('recruiter.documents.show', ['document' => $this->uuid, 'download' => 1]),
        ];
    }
}
