<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Compact row for directories (coordinator / TPO lists). No contact details, no documents. */
class StudentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'university_id' => $this->university_id,
            'full_name' => $this->full_name,
            'department' => $this->whenLoaded('department', fn () => $this->department->code),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch->code),
            'admission_type' => $this->admission_type->value,
            'current_semester' => $this->current_semester,
            'graduation_year' => $this->graduation_year,
            'pending_reviews' => $this->when(isset($this->pending_academic_count), fn () => (int) $this->pending_academic_count + (int) $this->pending_experience_count),
        ];
    }
}
