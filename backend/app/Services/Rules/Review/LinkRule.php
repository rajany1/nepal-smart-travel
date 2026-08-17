<?php

namespace App\Services\Rules\Review;

use App\Services\Rules\BaseRule;
use App\Services\Rules\RuleContext;

/**
 * Link spam: two or more URLs is a strong promo signal, one URL is weak.
 */
class LinkRule extends BaseRule
{
    public int $priority = 20;

    public function applies(RuleContext $context): bool
    {
        return trim((string) $context->get('text', '')) !== '';
    }

    public function execute(RuleContext $context): array
    {
        $urls = preg_match_all(
            '/(?:https?:\/\/|www\.)[^\s]+/i',
            (string) $context->get('text'),
            $m
        );

        if ($urls >= 2) {
            return ['points' => 30, 'reason' => "multiple links detected ({$urls})"];
        }
        if ($urls === 1) {
            return ['points' => 10, 'reason' => 'link detected'];
        }

        return ['points' => 0, 'reason' => ''];
    }
}
