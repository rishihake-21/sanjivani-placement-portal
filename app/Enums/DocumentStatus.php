<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case SUPERSEDED = 'SUPERSEDED';
}
