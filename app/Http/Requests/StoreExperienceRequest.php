<?php

namespace App\Http\Requests;

use App\Enums\ExperienceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ExperienceType::class)],
            'organization' => ['required', 'string', 'max:150'],
            'role_title' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'is_ongoing' => ['sometimes', 'boolean'],
            'end_date' => [
                Rule::requiredIf(fn () => ! $this->boolean('is_ongoing')),
                'nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:today',
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'certificate' => ['sometimes', 'file', 'max:' . config('tpms.documents.max_kb'),
                'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ];
    }
}
