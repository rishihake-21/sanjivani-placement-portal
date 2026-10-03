<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** department_id is deliberately not editable: move a person by deactivating and creating. */
class UpdateCoordinatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $coordinator = $this->route('coordinator');
        $userId = $coordinator ? \App\Models\Coordinator::query()->whereKey($coordinator)->value('user_id') : null;

        return [
            'department_id' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', Password::min(12)->mixedCase()->numbers()],
            'employee_id' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('coordinators', 'employee_id')->ignore($coordinator)],
            'phone' => ['sometimes', 'nullable', 'regex:/^(\+91)?[6-9][0-9]{9}$/'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
