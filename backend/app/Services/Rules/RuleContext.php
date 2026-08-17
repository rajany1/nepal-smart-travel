<?php

namespace App\Services\Rules;

/**
 * Typed input bag passed through the rule pipeline. Immutable-ish: rules may
 * mutate values via set() and later rules see the updated state.
 */
class RuleContext
{
    protected array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
