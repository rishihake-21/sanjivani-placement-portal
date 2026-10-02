<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersStudentDirectory
{
    /** Shared list filters: search, branch, semester, admission type, "has items awaiting review". */
    protected function applyDirectoryFilters(Builder $query, Request $request): Builder
    {
        $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'branch_id' => ['sometimes', 'integer'],
            'department_id' => ['sometimes', 'integer'],
            'semester' => ['sometimes', 'integer', 'between:1,8'],
            'admission_type' => ['sometimes', 'in:REGULAR,LATERAL'],
            'only_pending' => ['sometimes', 'boolean'],
        ]);

        return $query
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = '%' . addcslashes($request->string('search')->toString(), '%_\\') . '%';
                $q->where(fn (Builder $w) => $w->where('full_name', 'ilike', $term)->orWhere('university_id', 'ilike', $term));
            })
            ->when($request->filled('branch_id'), fn (Builder $q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('semester'), fn (Builder $q) => $q->where('current_semester', $request->integer('semester')))
            ->when($request->filled('admission_type'), fn (Builder $q) => $q->where('admission_type', $request->string('admission_type')->toString()))
            ->when($request->boolean('only_pending'), fn (Builder $q) => $q->where(
                fn (Builder $w) => $w
                    ->whereHas('academicRecords', fn ($r) => $r->where('status', 'PENDING'))
                    ->orWhereHas('experiences', fn ($r) => $r->where('status', 'PENDING'))
            ));
    }
}
