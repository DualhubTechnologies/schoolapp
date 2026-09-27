<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Order matters: roles must exist before a user can be given one.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SuperAdminSeeder::class,
            PayeTaxBracketSeeder::class,
        ]);
    }
}
