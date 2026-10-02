<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProofCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate' => ['required', 'file', 'max:' . config('tpms.documents.max_kb'),
                'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ];
    }
}
