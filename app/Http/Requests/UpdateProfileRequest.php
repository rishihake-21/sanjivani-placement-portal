<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Only student-editable fields. Identity/admission columns are not accepted here at all. */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy check happens in the controller
    }

    public function rules(): array
    {
        return [
            'date_of_birth' => ['sometimes', 'date', 'before:today', 'after:1960-01-01'],
            'gender' => ['sometimes', 'nullable', 'in:MALE,FEMALE,OTHER,PREFER_NOT_TO_SAY'],
            'confirm_identity' => ['sometimes', 'boolean'],

            'personal_email' => ['sometimes', 'email:rfc', 'max:190'],
            'phone' => ['sometimes', 'regex:/^(\+91)?[6-9][0-9]{9}$/'],
            'current_city' => ['sometimes', 'string', 'max:100'],
            'permanent_city' => ['sometimes', 'string', 'max:100'],

            'skills' => ['sometimes', 'array', 'max:30'],
            'skills.*' => ['string', 'max:40', 'distinct:ignore_case'],
            'languages' => ['sometimes', 'array', 'max:10'],
            'languages.*' => ['string', 'max:40', 'distinct:ignore_case'],
            'github_url' => ['sometimes', 'nullable', 'url:https', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url:https', 'max:255'],
            'portfolio_url' => ['sometimes', 'nullable', 'url:https', 'max:255'],

            'preferred_roles' => ['sometimes', 'array', 'max:10'],
            'preferred_roles.*' => ['string', 'max:60'],
            'preferred_locations' => ['sometimes', 'array', 'max:10'],
            'preferred_locations.*' => ['string', 'max:60'],
            'opted_out_of_placement' => ['sometimes', 'boolean'],
            'opt_out_reason' => ['required_if:opted_out_of_placement,true', 'nullable', 'string', 'max:500'],
        ];
    }
}
