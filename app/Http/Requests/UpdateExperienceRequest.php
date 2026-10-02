<?php

namespace App\Http\Requests;

use App\Enums\ExperienceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(ExperienceType::class)],
            'organization' => ['sometimes', 'string', 'max:150'],
            'role_title' => ['sometimes', 'string', 'max:150'],
            'start_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'is_ongoing' => ['sometimes', 'boolean'],
            'end_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
