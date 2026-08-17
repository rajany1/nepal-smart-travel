<?php

namespace App\Services\Rules\Review;

use App\Services\Rules\BaseRule;
use App\Services\Rules\RuleContext;

/**
 * Shouting: >50% uppercase letters in a long review reads as spam/abuse.
 */
class CapsLockRule extends BaseRule
{
    public int $priority = 30;

    public function applies(RuleContext $context): bool
    {
        return mb_strlen((string) $context->get('text', '')) > 10;
    }

    public function execute(RuleContext $context): array
    {
        $text = (string) $context->get('text');
        $letters = preg_replace('/[^\p{L}]/u', '', $text);
        $lettersLen = mb_strlen($letters);

        if ($lettersLen === 0) {
            return ['points' => 0, 'reason' => ''];
        }

        $upper = preg_replace('/[^\p{Lu}]/u', '', $letters);
        $ratio = mb_strlen($upper) / $lettersLen;

        if ($ratio > 0.5) {
            return ['points' => 15, 'reason' => 'excessive capitalization (shouting)'];
        }

        return ['points' => 0, 'reason' => ''];
    }
}
