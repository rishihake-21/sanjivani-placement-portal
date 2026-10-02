<?php

namespace App\Policies;

use App\Models\Experience;
use App\Models\User;
use App\Policies\Concerns\AuthorizesStudentAccess;

class ExperiencePolicy
{
    use AuthorizesStudentAccess;

    public function view(User $user, Experience $experience): bool
    {
        return $this->canViewStudent($user, $experience->student);
    }

    public function modify(User $user, Experience $experience): bool
    {
        return $this->owns($user, $experience->student);
    }

    public function review(User $user, Experience $experience): bool
    {
        return $this->coordinatesDepartmentOf($user, $experience->student);
    }
}
