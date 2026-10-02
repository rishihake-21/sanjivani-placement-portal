<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Never exposes disk/path/hash: files are reachable only through the authorised download route. */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'document_type' => $this->document_type->value,
            'version' => $this->version,
            'status' => $this->status->value,
            'is_primary' => $this->is_primary,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'rejection' => $this->when($this->rejection_reason_code !== null, fn () => [
                'code' => $this->rejection_reason_code->value,
                'label' => $this->rejection_reason_code->label(),
                'text' => $this->rejection_reason,
            ]),
            'download_url' => route('documents.download', ['document' => $this->uuid]),
        ];
    }
}
