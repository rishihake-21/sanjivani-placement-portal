<?php

namespace App\Http\Requests;

use App\Enums\RejectionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', Rule::enum(RejectionReason::class)],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:reason_code,' . RejectionReason::OTHER->value],
        ];
    }
}
