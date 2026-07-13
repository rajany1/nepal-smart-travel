<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Console\Command;

class AssignFreeSubscriptions extends Command
{
    protected $signature = 'subscriptions:assign-free';
    protected $description = 'Assign Free plan to all users without an active subscription';

    public function handle(): int
    {
        $freePlan = SubscriptionPlan::where('slug', 'free')->first();

        if (!$freePlan) {
            $this->error('Free plan not found. Run SubscriptionPlanSeeder first.');
            return Command::FAILURE;
        }

        $count = 0;
        $users = User::all();

        foreach ($users as $user) {
            $hasActive = UserSubscription::where('user_id', $user->id)
                ->whereIn('status', ['active', 'trialing'])
                ->exists();

            if (!$hasActive) {
                UserSubscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $freePlan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                ]);
                $count++;
                $this->info("Assigned Free plan to {$user->email}");
            }
        }

        $this->info("Done. {$count} users received the Free plan.");
        return Command::SUCCESS;
    }
}
