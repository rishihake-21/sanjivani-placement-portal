<?php

namespace App\Enums;

enum PlacementStatus: string
{
    case OFFERED = 'OFFERED';
    case PLACED = 'PLACED';     // official, TPO-verified
    case DECLINED = 'DECLINED';
}
