<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRoleId = Role::where('name', 'admin')->value('id');
        $userRoleId = Role::where('name', 'user')->value('id');

        // Regular user
        $user = User::where('email', 'user@gmail.com')->first();
        if (! $user) {
            User::create([
                'name' => 'Regular User',
                'email' => 'user@gmail.com',
                'phone' => '9800000000',
                'password' => Hash::make('rajendra'),
                'role_id' => $userRoleId,
                'uuid' => (string) Str::uuid(),
            ]);
            $this->command->info('Created regular test user: user@gmail.com / rajendra');
        } else {
            $this->command->info('Regular test user already exists');
        }

        // Super admin
        $admin = User::where('email', 'rajendrasubedi10011@gmail.com')->first();
        if (! $admin) {
            User::create([
                'name' => 'Super Admin',
                'email' => 'rajendrasubedi10011@gmail.com',
                'phone' => '9811111111',
                'password' => Hash::make('rajendra'),
                'role_id' => $adminRoleId,
                'uuid' => (string) Str::uuid(),
            ]);
            $this->command->info('Created super admin: rajendrasubedi10011@gmail.com / rajendra');
        } else {
            if ($admin->role_id !== $adminRoleId) {
                $admin->role_id = $adminRoleId;
                $admin->save();
                $this->command->info('Updated existing user to admin role');
            } else {
                $this->command->info('Admin user already exists');
            }
        }
    }
}
