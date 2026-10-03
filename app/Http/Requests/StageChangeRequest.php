<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StageChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(ApplicationStage::class)],
            'remarks' => ['nullable', 'string', 'max:500'],
            'force' => ['sometimes', 'boolean'], // correction of a mistake; needs remarks (checked in the service)
        ];
    }
}
