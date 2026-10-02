<?php

namespace App\Enums;

enum ExperienceType: string
{
    case INTERNSHIP = 'INTERNSHIP';
    case JOB = 'JOB';
    case PROJECT_WORK = 'PROJECT_WORK';
    case TRAINING = 'TRAINING';

    public function certificateType(): DocumentType
    {
        return $this === self::JOB ? DocumentType::CERT_EXPERIENCE : DocumentType::CERT_INTERNSHIP;
    }
}
