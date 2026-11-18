<?php

namespace App\Http\Resources\Recruiter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A college-verified internship / job / project with its certificate. */
class ExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $certificate = $this->certificate;

        return [
            'type' => $this->type->value,
            'organization' => $this->organization,
            'role_title' => $this->role_title,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_ongoing' => $this->is_ongoing,
            'description' => $this->description,
            'verified_at' => $this->reviewed_at,
            'certificate' => $certificate !== null && $certificate->status->value === 'APPROVED' ? new DocumentResource($certificate) : null,
        ];
    }
}
