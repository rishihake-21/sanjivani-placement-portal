<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\AuthorizesStudentAccess;

class DocumentPolicy
{
    use AuthorizesStudentAccess;

    public function view(User $user, Document $document): bool
    {
        return $this->canViewStudent($user, $document->student) || $this->recruiterMaySee($user, $document);
    }

    /** Only standalone documents (resume, photo, other certificates) are reviewed on their own. */
    public function review(User $user, Document $document): bool
    {
        return $document->isStandalone() && $this->coordinatesDepartmentOf($user, $document->student);
    }

    /**
     * A recruiter may open a document only if it is APPROVED (someone at the college has checked it) and
     * its student has a live application to a drive of the recruiter's own company.
     */
    private function recruiterMaySee(User $user, Document $document): bool
    {
        if (! $user->is_active || ! $user->hasRole(Role::RECRUITER)) {
            return false;
        }

        $recruiter = $user->recruiter()->with('company')->first();
        if ($recruiter === null || ! $recruiter->canAccess() || $document->status !== DocumentStatus::APPROVED) {
            return false;
        }

        return Application::query()->visibleToRecruiter($recruiter)->where('student_id', $document->student_id)->exists();
    }
}
