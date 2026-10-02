<?php

namespace App\Enums;

enum DocumentType: string
{
    case RESUME = 'RESUME';
    case MARKSHEET_10TH = 'MARKSHEET_10TH';
    case MARKSHEET_12TH = 'MARKSHEET_12TH';
    case MARKSHEET_DIPLOMA = 'MARKSHEET_DIPLOMA';
    case MARKSHEET_SEMESTER = 'MARKSHEET_SEMESTER';
    case CERT_INTERNSHIP = 'CERT_INTERNSHIP';
    case CERT_EXPERIENCE = 'CERT_EXPERIENCE';
    case CERT_OTHER = 'CERT_OTHER';

    /** Types a student may upload on their own (not attached to a record). */
    public function isStandalone(): bool
    {
        return in_array($this, [self::RESUME, self::CERT_OTHER], true);
    }

    /** @return list<string> */
    public static function standaloneValues(): array
    {
        return array_values(array_map(
            fn (self $t) => $t->value,
            array_filter(self::cases(), fn (self $t) => $t->isStandalone())
        ));
    }
}
