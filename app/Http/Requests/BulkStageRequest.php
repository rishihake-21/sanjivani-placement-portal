<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_ids' => ['required', 'array', 'min:1', 'max:500'],
            'application_ids.*' => ['integer', 'distinct'],
            'stage' => ['required', Rule::enum(ApplicationStage::class)],
            'remarks' => ['nullable', 'string', 'max:500'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
