<?php

namespace App\Policies;

use App\Models\PatientDocument;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class PatientDocumentPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, PatientDocument $document): bool
    {
        return $user->can('documents.view')
            && $this->access->canAccessPatient($user, $document->patient_id);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.manage');
    }

    public function delete(User $user, PatientDocument $document): bool
    {
        return $user->can('documents.manage')
            && $this->access->canAccessPatient($user, $document->patient_id);
    }
}
