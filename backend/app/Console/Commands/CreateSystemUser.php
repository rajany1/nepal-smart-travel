<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('app:create-system-user')]
#[Description('Create BIPAD System user for automated reports')]
class CreateSystemUser extends Command
{
    public function handle(): int
    {
        $email = 'bipad-system@nepalsmarttravel.local';
        $name = 'BIPAD System';

        $user = User::where('email', $email)->first();

        if ($user) {
            $this->info("System user already exists: {$user->id}");
            return self::SUCCESS;
        }

        $user = User::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(\Illuminate\Support\Str::random(32)),
            'is_system' => true,
            'status' => 'active',
            'email_verified_at' => now(),
            'language_preference' => 'en',
            'receive_alerts' => false,
            'receive_push_notifications' => false,
            'receive_email_notifications' => false,
        ]);

        // Assign admin role if role system exists
        if (\Schema::hasTable('roles')) {
            $adminRole = \App\Models\Role::where('name', 'admin')->first();
            if ($adminRole) {
                $user->role_id = $adminRole->id;
                $user->save();
            }
        }

        $this->info("Created system user: {$user->id} ({$user->email})");
        return self::SUCCESS;
    }
}