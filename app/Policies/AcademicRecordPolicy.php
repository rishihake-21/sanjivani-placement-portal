<?php

namespace App\Policies;

use App\Models\AcademicRecord;
use App\Models\User;
use App\Policies\Concerns\AuthorizesStudentAccess;

class AcademicRecordPolicy
{
    use AuthorizesStudentAccess;

    public function view(User $user, AcademicRecord $record): bool
    {
        return $this->canViewStudent($user, $record->student);
    }

    /** update / submit / attach document / delete: the owner only. */
    public function modify(User $user, AcademicRecord $record): bool
    {
        return $this->owns($user, $record->student);
    }

    public function review(User $user, AcademicRecord $record): bool
    {
        return $this->coordinatesDepartmentOf($user, $record->student);
    }

    public function unlock(User $user, AcademicRecord $record): bool
    {
        return $this->coordinatesDepartmentOf($user, $record->student);
    }
}
