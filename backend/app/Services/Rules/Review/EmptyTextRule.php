<?php

namespace App\Services\Rules\Review;

use App\Models\PlaceReview;
use App\Services\ContentSafetyService;
use App\Services\Rules\BaseRule;
use App\Services\Rules\RuleContext;

/**
 * Empty reviews are always approved (nothing to moderate).
 */
class EmptyTextRule extends BaseRule
{
    public int $priority = 1;

    public function applies(RuleContext $context): bool
    {
        return trim((string) $context->get('text', '')) === '';
    }

    public function execute(RuleContext $context): array
    {
        return ['points' => 0, 'reason' => 'empty review — approved', 'decision' => 'approve'];
    }
}
