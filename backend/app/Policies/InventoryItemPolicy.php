<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.manage');
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('inventory.manage');
    }

    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('inventory.manage');
    }
}
