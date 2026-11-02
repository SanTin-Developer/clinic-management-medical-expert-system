<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data every deployment needs before it can serve requests.
 *
 * Safe to run on every container start: each seeder is idempotent and leaves
 * admin-edited records untouched. Demo content lives in DatabaseSeeder instead.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            KnowledgeBaseSeeder::class,
        ]);
    }
}
