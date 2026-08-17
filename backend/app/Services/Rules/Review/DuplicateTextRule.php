<?php

namespace App\Services\Rules\Review;

use App\Models\PlaceReview;
use App\Services\Rules\BaseRule;
use App\Services\Rules\RuleContext;

/**
 * Exact-duplicate detection: the same review text already exists on the
 * platform (different review, any place). A strong copy-paste spam signal.
 */
class DuplicateTextRule extends BaseRule
{
    public int $priority = 50;

    public function applies(RuleContext $context): bool
    {
        return trim((string) $context->get('text', '')) !== '';
    }

    public function execute(RuleContext $context): array
    {
        $review = $context->get('review');
        if (!$review instanceof PlaceReview) {
            return ['points' => 0, 'reason' => ''];
        }

        $text = mb_strtolower(trim((string) $context->get('text')));
        $norm = preg_replace('/\s+/u', ' ', $text);

        $dupe = PlaceReview::where('id', '!=', $review->id)
            ->whereRaw('LOWER(COALESCE(description, title)) = ?', [$norm])
            ->where('created_at', '>=', now()->subDays(90))
            ->exists();

        if ($dupe) {
            return ['points' => 35, 'reason' => 'duplicate review text already posted'];
        }

        return ['points' => 0, 'reason' => ''];
    }
}
