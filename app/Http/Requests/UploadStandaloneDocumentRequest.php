<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadStandaloneDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A photo has to be a picture; the other standalone documents may also be PDFs.
        $imageOnly = DocumentType::tryFrom((string) $this->input('document_type'))?->isImageOnly() === true;

        return [
            'document_type' => ['required', Rule::in(DocumentType::standaloneValues())],
            'document' => ['required', 'file', 'max:' . config('tpms.documents.max_kb'),
                $imageOnly ? 'mimes:jpg,jpeg,png' : 'mimes:pdf,jpg,jpeg,png',
                $imageOnly ? 'mimetypes:image/jpeg,image/png' : 'mimetypes:application/pdf,image/jpeg,image/png'],
        ];
    }
}
