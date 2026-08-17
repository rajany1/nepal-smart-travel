<?php

namespace App\Services\Rules\Review;

use App\Services\Rules\BaseRule;
use App\Services\Rules\RuleContext;

/**
 * Character spam: 3+ consecutive identical characters ("gggg", "!!!!!") is a
 * filler/spam tell. Each run adds 5 points, capped at 20.
 */
class RepeatedCharacterRule extends BaseRule
{
    public int $priority = 40;

    public function applies(RuleContext $context): bool
    {
        return trim((string) $context->get('text', '')) !== '';
    }

    public function execute(RuleContext $context): array
    {
        $runs = 0;
        preg_match_all('/(.)\1{2,}/u', (string) $context->get('text'), $m);
        $runs = count($m[0] ?? []);

        if ($runs === 0) {
            return ['points' => 0, 'reason' => ''];
        }

        return ['points' => min(20, $runs * 5), 'reason' => "repeated character spam ({$runs} run(s))"];
    }
}
