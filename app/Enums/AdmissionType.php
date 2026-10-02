<?php

namespace App\Enums;

enum AdmissionType: string
{
    case REGULAR = 'REGULAR';
    case LATERAL = 'LATERAL';

    /** First degree semester the student actually studies at this university. */
    public function firstSemester(): int
    {
        return $this === self::LATERAL ? 3 : 1;
    }
}
