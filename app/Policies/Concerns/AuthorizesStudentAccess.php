<?php

namespace App\Policies\Concerns;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;

/**
 * Central access rules for student data:
 *   student        -> only their own data
 *   T&P Coordinator-> students of THEIR department (view + review)
 *   TPO            -> all students (read-only here)
 *   HOD (future)   -> own department, read-only
 *   System Admin   -> NO access to student data (technical role only)
 */
trait AuthorizesStudentAccess
{
    protected function owns(User $user, Student $student): bool
    {
        return $user->is_active && $user->hasRole(Role::STUDENT) && $student->user_id === $user->id;
    }

    protected function coordinatesDepartmentOf(User $user, Student $student): bool
    {
        return $user->is_active
            && $user->hasRole(Role::TP_COORDINATOR)
            && $user->department_id !== null
            && (int) $user->department_id === (int) $student->department_id;
    }

    protected function canViewStudent(User $user, Student $student): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $this->owns($user, $student)
            || $this->coordinatesDepartmentOf($user, $student)
            || $user->hasRole(Role::TPO)
            || ($user->hasRole(Role::HOD) && (int) $user->department_id === (int) $student->department_id);
    }
}
