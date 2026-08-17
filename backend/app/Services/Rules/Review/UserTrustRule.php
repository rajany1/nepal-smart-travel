<?php

namespace App\Services\Rules\Review;

use App\Models\User;
use App\Services\Rules\BaseRule;
use App\Services\Rules\RuleContext;

/**
 * User trust: moderation strikes, fresh accounts, and review bursts all add
 * suspicion. Only applies when the review belongs to a user.
 */
class UserTrustRule extends BaseRule
{
    public int $priority = 60;

    public function applies(RuleContext $context): bool
    {
        return $context->get('user') instanceof User;
    }

    public function execute(RuleContext $context): array
    {
        /** @var User $user */
        $user = $context->get('user');
        $points = 0;
        $reasons = [];

        if ($user->moderationStrikes()->where('created_at', '>=', now()->subDays(90))->count() > 0) {
            $points += 20;
            $reasons[] = 'user has recent moderation strikes';
        }

        if ($user->created_at && $user->created_at->gte(now()->subDays(7))) {
            $points += 10;
            $reasons[] = 'very new account';
        }

        $burst = $user->reviews()
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($burst >= 5) {
            $points += 30;
            $reasons[] = "review burst ({$burst} in the last hour)";
        }

        return ['points' => $points, 'reason' => implode(', ', $reasons)];
    }
}
