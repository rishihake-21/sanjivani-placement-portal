<?php

namespace App\Enums;

enum RejectionReason: string
{
    case UNREADABLE = 'UNREADABLE';
    case WRONG_DOCUMENT = 'WRONG_DOCUMENT';
    case DATA_MISMATCH = 'DATA_MISMATCH';
    case INCOMPLETE = 'INCOMPLETE';
    case INVALID_OR_EXPIRED = 'INVALID_OR_EXPIRED';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::UNREADABLE => 'Document is not readable',
            self::WRONG_DOCUMENT => 'Document does not match the selected type',
            self::DATA_MISMATCH => 'Entered values do not match the document',
            self::INCOMPLETE => 'Document is incomplete (missing pages or details)',
            self::INVALID_OR_EXPIRED => 'Document is invalid or expired',
            self::OTHER => 'Other',
        };
    }
}
