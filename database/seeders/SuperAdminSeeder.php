<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The platform owner's account. No password lives in the code: it comes
 * from SUPER_ADMIN_PASSWORD in .env (config('app.super_admin')), or a
 * random one is made and shown once in the console. An existing account is left exactly as it is.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('app.super_admin.email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $password = (string) config('app.super_admin.password');
            $generated = $password === '';

            if ($generated) {
                $password = Str::password(16, symbols: false);
            }

            $user = User::create([
                'name' => 'Admin',
                'email' => $email,
                'password' => Hash::make($password),
                'school_id' => null,
            ]);

            if ($generated) {
                $this->command?->warn("Platform owner {$email} created with password: {$password}");
                $this->command?->warn('Write it down now: it is not shown again. Change it after signing in.');
            }
        }

        $user->assignRole('Super Admin');
    }
}
