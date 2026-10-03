<?php

namespace App\Enums;

enum ApplicationStage: string
{
    case APPLIED = 'APPLIED';
    case SHORTLISTED = 'SHORTLISTED';
    case APTITUDE = 'APTITUDE';
    case TECHNICAL = 'TECHNICAL';
    case HR = 'HR';
    case SELECTED = 'SELECTED';
    case REJECTED = 'REJECTED';
    case OFFER_RECEIVED = 'OFFER_RECEIVED';
    case PLACED = 'PLACED';
    case WITHDRAWN = 'WITHDRAWN';

    /** Still moving through interview rounds. */
    public function isInRecruitment(): bool
    {
        return in_array($this, [self::SHORTLISTED, self::APTITUDE, self::TECHNICAL, self::HR], true);
    }

    public function isSelected(): bool
    {
        return in_array($this, [self::SELECTED, self::OFFER_RECEIVED, self::PLACED], true);
    }

    /** @return list<string> */
    public static function recruitmentValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isInRecruitment())));
    }

    /** @return list<string> */
    public static function selectedValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isSelected())));
    }
}
