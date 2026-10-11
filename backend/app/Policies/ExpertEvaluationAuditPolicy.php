<?php

namespace App\Policies;

use App\Models\ExpertEvaluationAudit;
use App\Models\User;

class ExpertEvaluationAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit-logs.view');
    }

    public function view(User $user, ExpertEvaluationAudit $audit): bool
    {
        return $user->can('audit-logs.view');
    }
}
