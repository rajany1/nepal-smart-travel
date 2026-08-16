<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    public function generate(string $prompt, array $options = []): string;

    public function generateJson(string $prompt, array $options = []): array;

    public function generateWithImages(string $prompt, array $imagePaths, array $options = []): string;

    public function generateJsonWithImages(string $prompt, array $imagePaths, array $options = []): array;
}
