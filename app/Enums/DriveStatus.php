<?php

namespace App\Enums;

enum DriveStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';
}
