<?php

namespace App\Http\Requests;

use App\Enums\AcademicLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isDegree = $this->input('level') === AcademicLevel::DEGREE_SEM->value;

        return array_merge(
            [
                'level' => ['required', Rule::enum(AcademicLevel::class)],
                'semester' => $isDegree
                    ? ['required', 'integer', 'between:1,8']
                    : ['prohibited'],
                'document' => ['sometimes', 'file', 'max:' . config('tpms.documents.max_kb'),
                    'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
            ],
            AcademicRecordRules::fields(partial: false),
            [
                // the one value that defines a row differs by level
                'percentage' => $isDegree
                    ? ['nullable', 'numeric', 'between:0,100']
                    : ['required', 'numeric', 'between:0,100'],
                'sgpa' => $isDegree
                    ? ['required', 'numeric', 'between:0,10']
                    : ['nullable', 'numeric', 'between:0,10'],
            ],
        );
    }
}
