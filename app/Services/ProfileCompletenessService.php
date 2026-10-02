<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\RecordStatus;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Computes the status of every profile section on the fly (nothing is stored, so it never goes stale).
 *
 * Section status values: COMPLETE | IN_REVIEW | ACTION_REQUIRED | INCOMPLETE | OPTIONAL_EMPTY
 * Minimum to apply to any drive: A (identity confirmed), C (required academics verified), G (primary resume approved).
 */
class ProfileCompletenessService
{
    private const RANK = ['COMPLETE' => 0, 'OPTIONAL_EMPTY' => 0, 'IN_REVIEW' => 1, 'INCOMPLETE' => 2, 'ACTION_REQUIRED' => 3];

    public function summarize(Student $student): array
    {
        $records = $student->academicRecords()->live()->get();
        $experiences = $student->experiences()->live()->get();
        $resume = $student->documents()
            ->where('document_type', DocumentType::RESUME->value)
            ->where('is_primary', true)
            ->where('status', '<>', DocumentStatus::SUPERSEDED->value)
            ->first();

        $sections = [
            'A_identity' => $this->identity($student),
            'B_contact' => $this->contact($student),
            'C_academic' => $this->academic($student, $records),
            'D_skills_links' => $this->skills($student),
            'E_experience' => $this->experience($experiences),
            'F_preferences' => $this->preferences($student),
            'G_resume' => $this->resume($resume),
        ];

        $blockers = [];
        foreach (['A_identity', 'C_academic', 'G_resume'] as $key) {
            if ($sections[$key]['status'] !== 'COMPLETE') {
                $blockers[] = ['section' => $key, 'status' => $sections[$key]['status'], 'hint' => $sections[$key]['hint']];
            }
        }

        return [
            'sections' => $sections,
            'can_apply' => $blockers === [] && ! $student->isLocked(),
            'blockers' => $blockers,
        ];
    }

    private function identity(Student $s): array
    {
        $done = $s->identity_confirmed_at !== null && $s->date_of_birth !== null;

        return $this->section($done ? 'COMPLETE' : 'INCOMPLETE', 'Confirm your identity details and add your date of birth.');
    }

    private function contact(Student $s): array
    {
        $done = $s->personal_email && $s->phone && $s->current_city && $s->permanent_city;

        return $this->section($done ? 'COMPLETE' : 'INCOMPLETE', 'Add personal email, phone and both cities.');
    }

    private function academic(Student $s, Collection $records): array
    {
        $items = [];
        $status = 'COMPLETE';

        foreach ($s->requiredAcademicKeys() as [$level, $semester]) {
            $forKey = $records->filter(fn ($r) => $r->level === $level && ($r->semester === null ? null : (int) $r->semester) === $semester);
            $verified = $forKey->firstWhere('status', RecordStatus::VERIFIED);
            $open = $forKey->first(fn ($r) => $r->status !== RecordStatus::VERIFIED);

            $itemStatus = match (true) {
                $verified !== null => 'VERIFIED',
                $open === null => 'MISSING',
                default => $open->status->value,
            };

            $items[] = [
                'record' => $level->label($semester),
                'status' => $itemStatus,
                'revision_in_progress' => $verified !== null && $open !== null ? $open->status->value : null,
            ];

            $sectionStatus = match ($itemStatus) {
                'VERIFIED' => 'COMPLETE',
                'PENDING' => 'IN_REVIEW',
                'REJECTED' => 'ACTION_REQUIRED',
                default => 'INCOMPLETE', // MISSING, DRAFT
            };
            $status = $this->worst($status, $sectionStatus);
        }

        return $this->section($status, 'Add and submit the missing academic records with their marksheets.') + ['items' => $items];
    }

    private function skills(Student $s): array
    {
        return $this->section(! empty($s->skills) ? 'COMPLETE' : 'INCOMPLETE', 'Add at least one skill.');
    }

    private function experience(Collection $experiences): array
    {
        if ($experiences->isEmpty()) {
            return $this->section('OPTIONAL_EMPTY', 'Optional: add internships or work experience.');
        }

        $status = match (true) {
            $experiences->contains('status', RecordStatus::REJECTED) => 'ACTION_REQUIRED',
            $experiences->contains('status', RecordStatus::PENDING) => 'IN_REVIEW',
            default => 'COMPLETE',
        };

        return $this->section($status, 'Fix rejected entries or wait for review.') + [
            'self_declared_count' => $experiences->where('status', RecordStatus::SELF_DECLARED)->count(),
        ];
    }

    private function preferences(Student $s): array
    {
        $done = ! empty($s->preferred_roles) || $s->opted_out_of_placement;

        return $this->section($done ? 'COMPLETE' : 'INCOMPLETE', 'Choose preferred roles or mark yourself as opted out.');
    }

    private function resume($resume): array
    {
        $status = match (true) {
            $resume === null => 'INCOMPLETE',
            $resume->status === DocumentStatus::APPROVED => 'COMPLETE',
            $resume->status === DocumentStatus::REJECTED => 'ACTION_REQUIRED',
            default => 'IN_REVIEW',
        };

        return $this->section($status, 'Upload a resume and wait for the coordinator to approve it.');
    }

    private function section(string $status, string $hint): array
    {
        return ['status' => $status, 'hint' => $hint];
    }

    private function worst(string $a, string $b): string
    {
        return self::RANK[$b] > self::RANK[$a] ? $b : $a;
    }
}
