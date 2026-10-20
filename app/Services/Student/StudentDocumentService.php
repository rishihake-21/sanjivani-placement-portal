<?php

namespace App\Services\Student;

use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read model for a student's own documents. Shared by the documents endpoint and the portal pages,
 * so the superseded-row rule lives in one place.
 */
class StudentDocumentService
{
    public function documents(Student $student, bool $history): Collection
    {
        $query = $student->documents()->orderByDesc('created_at');

        if (! $history) {
            $query->where('status', '<>', 'SUPERSEDED');
        }

        return $query->get();
    }
}
