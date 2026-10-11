<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait CreatesUsers
{
    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function actingAsRole(string $role): User
    {
        $user = $this->userWithRole($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
