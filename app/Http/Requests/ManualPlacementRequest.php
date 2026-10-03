<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'role_title' => ['required', 'string', 'max:150'],
            'ctc_lpa' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'location' => ['nullable', 'string', 'max:150'],
            'offer_date' => ['required', 'date', 'before_or_equal:today'],
            'joining_date' => ['nullable', 'date', 'after_or_equal:offer_date'],
            'status' => ['sometimes', Rule::in(['OFFERED', 'PLACED'])],
        ];
    }
}
