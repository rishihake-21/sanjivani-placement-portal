<?php

namespace App\Enums;

enum RecordStatus: string
{
    case SELF_DECLARED = 'SELF_DECLARED'; // experiences only: visible but unverified
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';
    case SUPERSEDED = 'SUPERSEDED';

    /** Statuses in which the student may still change the data in place. */
    public function isStudentEditable(): bool
    {
        return in_array($this, [self::SELF_DECLARED, self::DRAFT, self::REJECTED], true);
    }

    /** "Open" = not yet a final answer (used by the partial unique indexes). */
    public function isOpen(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING, self::REJECTED], true);
    }
}
