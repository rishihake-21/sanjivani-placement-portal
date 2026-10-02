<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'organization' => $this->organization,
            'role_title' => $this->role_title,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_ongoing' => $this->is_ongoing,
            'description' => $this->description,
            // SELF_DECLARED must be labelled as unverified wherever it is displayed or reported
            'status' => $this->status->value,
            'version' => $this->version,
            'supersedes_id' => $this->supersedes_id,
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'rejection' => $this->when($this->rejection_reason_code !== null, fn () => [
                'code' => $this->rejection_reason_code->value,
                'label' => $this->rejection_reason_code->label(),
                'text' => $this->rejection_reason,
            ]),
            'certificate' => new DocumentResource($this->whenLoaded('certificate')),
        ];
    }
}
