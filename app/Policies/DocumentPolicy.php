<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\AuthorizesStudentAccess;

class DocumentPolicy
{
    use AuthorizesStudentAccess;

    public function view(User $user, Document $document): bool
    {
        return $this->canViewStudent($user, $document->student);
    }

    /** Only standalone documents (resume, other certificates) are reviewed on their own. */
    public function review(User $user, Document $document): bool
    {
        return $document->isStandalone() && $this->coordinatesDepartmentOf($user, $document->student);
    }
}
