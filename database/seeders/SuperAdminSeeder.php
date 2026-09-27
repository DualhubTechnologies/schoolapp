<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'adrianmugizi8@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Dualhub@123'),
                'school_id' => null,
            ],
        );

        $user->assignRole('Super Admin');
    }
}
