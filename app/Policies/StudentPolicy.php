<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Policies\Concerns\AuthorizesStudentAccess;

class StudentPolicy
{
    use AuthorizesStudentAccess;

    public function view(User $user, Student $student): bool
    {
        return $this->canViewStudent($user, $student);
    }

    public function update(User $user, Student $student): bool
    {
        return $this->owns($user, $student) && ! $student->isLocked();
    }
}
