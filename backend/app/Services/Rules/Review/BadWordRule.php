<?php

namespace App\Services\Rules\Review;

use App\Services\ContentSafetyService;
use App\Services\Rules\BaseRule;
use App\Services\Rules\RuleContext;

/**
 * Bad-word hits via the existing ContentSafetyService wordlist scan.
 * Points scale with severity: severity * 12 per hit, capped at 60.
 */
class BadWordRule extends BaseRule
{
    public int $priority = 10;

    public function applies(RuleContext $context): bool
    {
        return trim((string) $context->get('text', '')) !== '';
    }

    public function execute(RuleContext $context): array
    {
        $hits = app(ContentSafetyService::class)->scan((string) $context->get('text'));

        if (empty($hits)) {
            return ['points' => 0, 'reason' => ''];
        }

        $points = (int) min(60, array_sum(array_map(
            fn ($h) => max(1, (int) $h['severity']) * 12,
            $hits
        )));

        $words = collect($hits)->pluck('word')->unique()->values()->implode(', ');

        return ['points' => $points, 'reason' => "bad word(s) detected: {$words}"];
    }
}
