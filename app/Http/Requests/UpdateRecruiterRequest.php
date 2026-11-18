<?php

namespace App\Http\Requests;

use App\Models\Recruiter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** company_id is deliberately not editable: it decides whose applicants are visible. Create a new account instead. */
class UpdateRecruiterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = Recruiter::query()->whereKey($this->route('recruiter'))->value('user_id');

        return [
            'company_id' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', Password::min(12)->mixedCase()->numbers()],
            'phone' => ['sometimes', 'nullable', 'regex:/^(\+91)?[6-9][0-9]{9}$/'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
