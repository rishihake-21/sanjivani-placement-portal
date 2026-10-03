<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Field rules shared by drive create and update. */
final class DriveRules
{
    public static function rules(bool $partial): array
    {
        $required = $partial ? ['sometimes'] : ['required'];
        $optional = $partial ? ['sometimes', 'nullable'] : ['nullable'];
        $year = (int) date('Y');

        return [
            'company_id' => [...$required, 'integer', Rule::exists('companies', 'id')],
            'title' => [...$required, 'string', 'max:150'],
            'description' => [...$optional, 'string', 'max:5000'],
            'employment_type' => ['sometimes', Rule::in(['FULL_TIME', 'INTERNSHIP', 'INTERNSHIP_PPO'])],
            'ctc_lpa' => [...$optional, 'numeric', 'min:0', 'max:1000'],
            'ctc_max_lpa' => [...$optional, 'numeric', 'min:0', 'max:1000', 'gte:ctc_lpa', 'required_with:ctc_lpa'],
            'location' => [...$optional, 'string', 'max:150'],
            'drive_date' => [...$optional, 'date', 'after_or_equal:today'],
            'application_deadline' => [...$optional, 'date', 'after:now'],
            'graduation_year' => [...$required, 'integer', 'between:' . ($year - 1) . ',' . ($year + 6)],
            'additional_requirements' => [...$optional, 'string', 'max:3000'],
            'allow_placed_students' => ['sometimes', 'boolean'],

            'eligibility' => ['sometimes', 'array'],
            'eligibility.min_cgpa' => ['nullable', 'numeric', 'between:0,10'],
            'eligibility.min_tenth_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'eligibility.min_twelfth_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'eligibility.min_diploma_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'eligibility.max_active_backlogs' => ['nullable', 'integer', 'between:0,40'],
            'eligibility.max_total_backlogs' => ['nullable', 'integer', 'between:0,40'],
            'eligibility.diploma_counts_as_twelfth' => ['sometimes', 'boolean'],

            'branch_ids' => ['sometimes', 'array', 'max:50'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('is_active', true)],
        ];
    }
}
