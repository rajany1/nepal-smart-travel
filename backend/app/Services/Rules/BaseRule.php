<?php

namespace App\Services\Rules;

/**
 * Base contract for a single deterministic rule. Rules are pure code — no
 * LLM, no external HTTP — so every agent behaves identically on every run.
 */
abstract class BaseRule
{
    public int $priority = 100;

    /**
     * Whether this rule participates for the current context.
     */
    abstract public function applies(RuleContext $context): bool;

    /**
     * Run the rule. Return an associative array merged into the pipeline
     * results (under the rule's own key when using RuleEngine::runMapped).
     */
    abstract public function execute(RuleContext $context): array;
}
