<?php

namespace App\Enums;

enum ReuploadStatus: string
{
    case OPEN = 'OPEN';
    case FULFILLED = 'FULFILLED';
    case CANCELLED = 'CANCELLED';
}
