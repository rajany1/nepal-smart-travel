<?php

namespace App\Services;

use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\GroqService;
use App\Services\Ai\GeminiService;
use App\Exceptions\AiRateLimitException;
use Illuminate\Support\Facades\Log;

class RomanNepaliTranslationService
{
    protected AiProviderInterface $ai;

    public function __construct(?AiProviderInterface $ai = null)
    {
        $this->ai = $ai ?? $this->getDefaultProvider();
    }

    protected function getDefaultProvider(): AiProviderInterface
    {
        if (config('services.groq.api_key')) {
            return new GroqService();
        }
        if (config('services.gemini.api_key')) {
            return new GeminiService();
        }
        throw new \RuntimeException('No AI provider configured for Roman Nepali translation. Set GROQ_API_KEY or GEMINI_API_KEY.');
    }

    /**
     * Translate Roman Nepali text to Devanagari Nepali.
     * Returns the translated text or null on failure.
     */
    public function translate(string $romanNepali): ?string
    {
        if (empty(trim($romanNepali))) {
            return null;
        }

        // Skip if already contains Devanagari
        if ($this->containsDevanagari($romanNepali)) {
            return $romanNepali;
        }

        $prompt = $this->buildPrompt($romanNepali);

        try {
            $translated = $this->ai->generate($prompt, [
                'temperature' => 0.1,
                'maxOutputTokens' => 1024,
            ]);

            $translated = $this->cleanTranslation($translated);

            if (empty($translated) || $translated === $romanNepali) {
                return null;
            }

            return $translated;
        } catch (AiRateLimitException $e) {
            Log::warning('Roman Nepali translation rate limited: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Roman Nepali translation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Translate both title and description from Roman Nepali to Devanagari.
     */
    public function translateAlert(string $title, string $description): array
    {
        $results = [
            'title_ne' => null,
            'description_ne' => null,
            'errors' => [],
        ];

        try {
            $results['title_ne'] = $this->translate($title);
        } catch (\Exception $e) {
            $results['errors'][] = 'Title translation failed: ' . $e->getMessage();
        }

        try {
            $results['description_ne'] = $this->translate($description);
        } catch (\Exception $e) {
            $results['errors'][] = 'Description translation failed: ' . $e->getMessage();
        }

        return $results;
    }

    protected function buildPrompt(string $text): string
    {
        return <<<PROMPT
You are an expert translator for Nepali language. Translate the following Roman Nepali (Nepali written in Latin script) text to proper Devanagari Nepali script.

IMPORTANT RULES:
1. Preserve the EXACT meaning - this is for emergency/public safety alerts
2. Do NOT change warnings, negations, instructions, or severity
3. Preserve all proper nouns (place names, district names, personal names)
4. Preserve numbers, dates, units exactly
5. If the input contains English words mixed with Roman Nepali, translate only the Nepali parts
6. Return ONLY the translated Devanagari text, no explanations, no markdown

Examples:
Input: "Nepal ma bhari barsa ko karan aabasyak kaam bahek bahira nabiskinu hola."
Output: "नेपालमा भारी वर्षाका कारण आवश्यक काम बाहेक बाहिर ननिस्किनु होला।"

Input: "Phedikhola ra aaspaas ko kshetra ma badhi ko jokhim badheko cha."
Output: "फेदीखोला र आसपासका क्षेत्रमा बाढीको जोखिम बढेको छ।"

Input: "Kathmandu ma aaja traffic jam cha, kripaya alchi sadak prayog garnuhos."
Output: "काठमाडौंमा आज ट्राफिक जाम छ, कृपया अलि सडक प्रयोग गर्नुहोस्।"

Input: "Heavy rainfall warning for Kavre district. Avoid travel."
Output: "काव्रे जिल्लाका लागि भारी वर्षाको चेतावनी। यात्रा नगर्नुहोस्।"

Now translate this text:
{$text}
PROMPT;
    }

    protected function cleanTranslation(string $text): string
    {
        $text = trim($text);
        
        // Remove any markdown code blocks
        $text = preg_replace('/^```[\s\S]*?```$/m', '', $text);
        $text = preg_replace('/^```.*$/m', '', $text);
        $text = preg_replace('/```$/m', '', $text);
        
        // Remove common AI response prefixes
        $text = preg_replace('/^(Translation:|Translated:|Output:|Result:|Nepali:|Devanagari:)\s*/i', '', $text);
        
        // Remove quotes if the entire response is wrapped
        $text = preg_replace('/^["\'](.*)["\']$/', '$1', $text);
        
        return trim($text);
    }

    protected function containsDevanagari(string $text): bool
    {
        return preg_match('/[\x{0900}-\x{097F}]/u', $text) === 1;
    }

    /**
     * Check if AI provider is available
     */
    public function isAvailable(): bool
    {
        try {
            $result = $this->ai->generate('test', ['maxOutputTokens' => 5, 'temperature' => 0]);
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
}