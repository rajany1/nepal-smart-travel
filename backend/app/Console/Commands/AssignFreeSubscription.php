<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Console\Command;

class AssignFreeSubscription extends Command
{
    protected $signature = 'subscription:assign-free';
    protected $description = 'Assign the Free subscription plan to all users who have no subscription';

    public function handle(): int
    {
        $freePlan = SubscriptionPlan::where('slug', 'free')->first();
        if (!$freePlan) {
            $this->error('Free plan not found. Run SubscriptionPlanSeeder first.');
            return Command::FAILURE;
        }

        $userIds = User::whereDoesntHave('subscription')->pluck('id');
        $count = $userIds->count();

        if ($count === 0) {
            $this->info('All users already have a subscription. Nothing to do.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($userIds as $userId) {
            UserSubscription::create([
                'user_id' => $userId,
                'subscription_plan_id' => $freePlan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => null,
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Assigned Free plan to {$count} user(s).");

        return Command::SUCCESS;
    }
}
