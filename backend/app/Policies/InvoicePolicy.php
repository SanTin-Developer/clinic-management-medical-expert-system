<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('billing.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.update');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.delete');
    }
}
