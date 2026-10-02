<?php

namespace App\Enums;

enum AcademicLevel: string
{
    case TENTH = 'TENTH';
    case TWELFTH = 'TWELFTH';
    case DIPLOMA = 'DIPLOMA';
    case DEGREE_SEM = 'DEGREE_SEM';

    public function documentType(): DocumentType
    {
        return match ($this) {
            self::TENTH => DocumentType::MARKSHEET_10TH,
            self::TWELFTH => DocumentType::MARKSHEET_12TH,
            self::DIPLOMA => DocumentType::MARKSHEET_DIPLOMA,
            self::DEGREE_SEM => DocumentType::MARKSHEET_SEMESTER,
        };
    }

    public function label(?int $semester = null): string
    {
        return match ($this) {
            self::TENTH => '10th',
            self::TWELFTH => '12th',
            self::DIPLOMA => 'Diploma',
            self::DEGREE_SEM => 'Semester ' . $semester,
        };
    }
}
