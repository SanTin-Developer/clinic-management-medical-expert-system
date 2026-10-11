<?php

namespace App\Policies;

use App\Models\PatientDocument;
use App\Models\User;

class PatientDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, PatientDocument $document): bool
    {
        return $user->can('documents.view');
    }

    public function create(User $user): bool
    {
        return $user->can('documents.manage');
    }

    public function delete(User $user, PatientDocument $document): bool
    {
        return $user->can('documents.manage');
    }
}
