<?php

namespace App\Services\Rules;

/**
 * Runs a priority-sorted collection of rules against a RuleContext.
 *
 * All rules that apply are executed; outputs are returned both raw and
 * mapped-by-rule-class so callers can trace which rule produced what.
 */
class RuleEngine
{
    /** @var BaseRule[] */
    protected array $rules = [];

    public function add(BaseRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    /** @param BaseRule[] $rules */
    public function addMany(array $rules): self
    {
        foreach ($rules as $rule) {
            $this->add($rule);
        }
        return $this;
    }

    /** @return BaseRule[] sorted by priority ascending */
    public function rules(): array
    {
        $rules = $this->rules;
        usort($rules, fn (BaseRule $a, BaseRule $b) => $a->priority <=> $b->priority);
        return $rules;
    }

    /**
     * Execute all applicable rules. Returns:
     *   ['results' => [...], 'mapped' => [ClassName => [...]]]
     */
    public function run(RuleContext $context): array
    {
        $results = [];
        $mapped = [];

        foreach ($this->rules() as $rule) {
            if (!$rule->applies($context)) {
                continue;
            }
            $out = $rule->execute($context);
            $mapped[get_class($rule)] = $out;
            $results[] = $out;
        }

        return ['results' => $results, 'mapped' => $mapped];
    }
}
