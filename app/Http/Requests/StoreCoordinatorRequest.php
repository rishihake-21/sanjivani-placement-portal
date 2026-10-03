<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCoordinatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()],
            'employee_id' => ['nullable', 'string', 'max:30', Rule::unique('coordinators', 'employee_id')],
            'phone' => ['nullable', 'regex:/^(\+91)?[6-9][0-9]{9}$/'],
            'designation' => ['nullable', 'string', 'max:80'],
        ];
    }
}
