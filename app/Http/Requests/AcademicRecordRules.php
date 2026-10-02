<?php

namespace App\Http\Requests;

/** Field rules shared by the store and update requests. */
final class AcademicRecordRules
{
    public static function fields(bool $partial): array
    {
        $sometimes = $partial ? ['sometimes'] : [];

        return [
            'institution_name' => [...$sometimes, 'nullable', 'string', 'max:150'],
            'board_or_university' => [...$sometimes, 'nullable', 'string', 'max:150'],
            'passing_year' => [...$sometimes, 'nullable', 'integer', 'between:1990,' . (date('Y') + 1)],
            'percentage' => [...$sometimes, 'nullable', 'numeric', 'between:0,100'],
            'obtained_marks' => [...$sometimes, 'nullable', 'numeric', 'min:0', 'required_with:total_marks'],
            'total_marks' => [...$sometimes, 'nullable', 'numeric', 'gt:0', 'required_with:obtained_marks', 'gte:obtained_marks'],
            'sgpa' => [...$sometimes, 'nullable', 'numeric', 'between:0,10'],
            'cgpa' => [...$sometimes, 'nullable', 'numeric', 'between:0,10'],
            'backlogs_in_term' => [...$sometimes, 'integer', 'between:0,20'],
            'active_backlogs_after_term' => [...$sometimes, 'integer', 'between:0,40'],
        ];
    }
}
