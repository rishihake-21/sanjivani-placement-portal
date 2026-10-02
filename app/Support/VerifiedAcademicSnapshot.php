<?php

namespace App\Support;

/**
 * What the eligibility engine is allowed to know about a student's academics:
 * VERIFIED values only. `complete` is false while any required record is unverified,
 * so the engine can answer "verification pending" instead of a flat "not eligible".
 */
final class VerifiedAcademicSnapshot
{
    /** @param list<string> $missing labels of required records that are not verified yet */
    public function __construct(
        public readonly ?float $tenthPercentage,
        public readonly ?float $twelfthPercentage,
        public readonly ?float $diplomaPercentage,
        public readonly ?float $cgpa,
        public readonly int $activeBacklogs,
        public readonly int $totalBacklogs,
        public readonly array $missing,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->missing === [];
    }

    public function toArray(): array
    {
        return [
            'tenth_percentage' => $this->tenthPercentage,
            'twelfth_percentage' => $this->twelfthPercentage,
            'diploma_percentage' => $this->diplomaPercentage,
            'cgpa' => $this->cgpa,
            'active_backlogs' => $this->activeBacklogs,
            'total_backlogs' => $this->totalBacklogs,
            'is_complete' => $this->isComplete(),
            'missing' => $this->missing,
        ];
    }
}
