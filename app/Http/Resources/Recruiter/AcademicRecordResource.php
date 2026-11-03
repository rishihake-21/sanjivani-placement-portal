<?php

namespace App\Http\Resources\Recruiter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A college-verified result with its marksheet. Review workflow details stay internal. */
class AcademicRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'level' => $this->level->value,
            'label' => $this->label(),
            'semester' => $this->semester,
            'institution_name' => $this->institution_name,
            'board_or_university' => $this->board_or_university,
            'passing_year' => $this->passing_year,
            'percentage' => $this->percentage,
            'obtained_marks' => $this->obtained_marks,
            'total_marks' => $this->total_marks,
            'sgpa' => $this->sgpa,
            'cgpa' => $this->cgpa,
            'backlogs_in_term' => $this->backlogs_in_term,
            'active_backlogs_after_term' => $this->active_backlogs_after_term,
            'verified_at' => $this->reviewed_at,
            'document' => $this->documentIfApproved($this->document),
        ];
    }

    private function documentIfApproved($document): ?DocumentResource
    {
        return $document !== null && $document->status->value === 'APPROVED' ? new DocumentResource($document) : null;
    }
}
