<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// Roles and permission codes together: the permission middleware is
// fail-closed, so an environment (or test run) is unusable without both.
class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserTypeSeeder::class,
            PermissionSeeder::class,
        ]);
    }
}
