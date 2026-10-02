<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A single supporting file (marksheet or certificate). Real content type is re-checked in DocumentService. */
class ProofFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:' . config('tpms.documents.max_kb'),
                'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ];
    }
}
