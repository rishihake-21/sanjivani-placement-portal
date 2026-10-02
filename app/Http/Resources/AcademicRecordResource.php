<?php

namespace App\Http\Resources;

use App\Enums\RecordStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'status' => $this->status->value,
            'version' => $this->version,
            'supersedes_id' => $this->supersedes_id,
            'locked' => $this->isLocked(),
            // true when saving would change this row in place or start a revision of it
            'can_edit' => $this->status->isStudentEditable() || ($this->status === RecordStatus::VERIFIED && ! $this->isLocked()),
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'rejection' => $this->when($this->rejection_reason_code !== null, fn () => [
                'code' => $this->rejection_reason_code->value,
                'label' => $this->rejection_reason_code->label(),
                'text' => $this->rejection_reason,
            ]),
            'document' => new DocumentResource($this->whenLoaded('document')),
        ];
    }
}
