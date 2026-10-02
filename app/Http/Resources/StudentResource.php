<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'university_id' => $this->university_id,
            'full_name' => $this->full_name,
            'department' => $this->whenLoaded('department', fn () => ['id' => $this->department->id, 'name' => $this->department->name, 'code' => $this->department->code]),
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name, 'code' => $this->branch->code]),
            'admission_type' => $this->admission_type->value,
            'admission_year' => $this->admission_year,
            'graduation_year' => $this->graduation_year,
            'current_semester' => $this->current_semester,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'identity_confirmed_at' => $this->identity_confirmed_at,
            'personal_email' => $this->personal_email,
            'phone' => $this->phone,
            'current_city' => $this->current_city,
            'permanent_city' => $this->permanent_city,
            'skills' => $this->skills,
            'languages' => $this->languages,
            'github_url' => $this->github_url,
            'linkedin_url' => $this->linkedin_url,
            'portfolio_url' => $this->portfolio_url,
            'preferred_roles' => $this->preferred_roles,
            'preferred_locations' => $this->preferred_locations,
            'opted_out_of_placement' => $this->opted_out_of_placement,
            'opt_out_reason' => $this->opt_out_reason,
            'profile_locked' => $this->isLocked(),
            'academic_records' => AcademicRecordResource::collection($this->whenLoaded('academicRecords')),
            'experiences' => ExperienceResource::collection($this->whenLoaded('experiences')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
