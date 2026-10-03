<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Used for recording an offer (all optional: defaults come from the drive) and for editing a placement. */
class PlacementDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_title' => ['sometimes', 'string', 'max:150'],
            'ctc_lpa' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'offer_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'joining_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:offer_date'],
        ];
    }
}
