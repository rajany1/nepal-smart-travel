<?php

namespace App\Services\Rules;

use App\Models\PlaceReview;

/**
 * Deterministic spam/toxicity scorer for place reviews. Runs a set of pure
 * rules (bad-word scan, link spam, caps lock, repeated chars, duplicate
 * text, user trust) and returns a 0-100 score with a verdict:
 *   reject (>=70) | review (40-69) | approve (<40)
 */
class ReviewScoringService
{
    public const REJECT_THRESHOLD = 70;
    public const REVIEW_THRESHOLD = 40;

    public function score(PlaceReview $review): array
    {
        $text = trim((string) ($review->description ?: $review->title ?: ''));

        $context = new RuleContext([
            'review' => $review,
            'text' => $text,
            'user' => $review->user,
        ]);

        $engine = (new RuleEngine())->addMany([
            new Review\EmptyTextRule(),
            new Review\BadWordRule(),
            new Review\LinkRule(),
            new Review\CapsLockRule(),
            new Review\RepeatedCharacterRule(),
            new Review\DuplicateTextRule(),
            new Review\UserTrustRule(),
        ]);

        $run = $engine->run($context);

        $points = array_sum(array_column($run['results'], 'points'));
        $score = (int) min(100, max(0, $points));
        $reasons = collect($run['results'])
            ->pluck('reason')
            ->filter(fn ($r) => $r !== '')
            ->values()
            ->all();

        $verdict = $score >= self::REJECT_THRESHOLD
            ? 'reject'
            : ($score >= self::REVIEW_THRESHOLD ? 'review' : 'approve');

        return [
            'score' => $score,
            'verdict' => $verdict,
            'reasons' => $reasons,
            'rules' => array_keys($run['mapped']),
        ];
    }
}
