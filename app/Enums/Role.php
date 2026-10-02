<?php

namespace App\Enums;

enum Role: string
{
    case STUDENT = 'student';
    case TP_COORDINATOR = 'tp_coordinator';
    case TPO = 'tpo';
    case SYSTEM_ADMIN = 'system_admin';
    case HOD = 'hod';              // future, read-only
    case RECRUITER = 'recruiter';  // future
}
