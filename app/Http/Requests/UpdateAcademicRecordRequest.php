<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** level and semester are the record's identity and cannot be changed. */
class UpdateAcademicRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['level' => ['prohibited'], 'semester' => ['prohibited']]
            + AcademicRecordRules::fields(partial: true);
    }
}
