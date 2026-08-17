<?php

namespace App\Services;

use App\Models\TranslationGlossary;

/**
 * Deterministic Nepali glossary engine (no LLM).
 *
 * Two layers:
 *  1. Glossary replacement — curated term -> Nepali pairs from the
 *     translation_glossary table, replaced word-boundary style.
 *  2. Best-effort Latin -> Devanagari transliteration for names that have no
 *     glossary entry.
 *
 * Full sentence-level machine translation is intentionally out of scope for
 * the rules engine — free text keeps its original wording outside known
 * terms, and admins can curate more terms in the glossary over time.
 */
class GlossaryTranslator
{
    protected ?array $terms = null;

    public function termMap(): array
    {
        if ($this->terms !== null) {
            return $this->terms;
        }

        $rows = TranslationGlossary::where('is_active', true)
            ->get(['term', 'nepali']);

        $map = [];
        foreach ($rows as $row) {
            $term = trim((string) $row->term);
            if ($term === '') {
                continue;
            }
            $map[$term] = trim((string) $row->nepali);
        }

        // Longest terms first so "hotel resort" style multi-word entries
        // win over single-word ones.
        uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $this->terms = $map;
    }

    /**
     * Replace known glossary terms in $text. Skips text that already looks
     * like Devanagari. Returns the transformed text and terms used.
     */
    public function translateText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['text' => $text, 'terms_used' => []];
        }

        if ($this->containsDevanagari($text)) {
            return ['text' => $text, 'terms_used' => []];
        }

        $used = [];
        foreach ($this->termMap() as $term => $nepali) {
            if (preg_match_all('/(?<![\p{L}\p{N}_])' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/iu', $text, $m)) {
                $text = preg_replace(
                    '/(?<![\p{L}\p{N}_])' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/iu',
                    $nepali,
                    $text
                );
                $used[] = $term;
            }
        }

        return ['text' => $text, 'terms_used' => $used];
    }

    public function containsDevanagari(string $text): bool
    {
        return preg_match('/[\x{0900}-\x{097F}]/u', $text) === 1;
    }

    /**
     * Best-effort Latin -> Devanagari transliteration for place names.
     * Pairs longest known digraphs first, then single letters.
     */
    public function transliterate(string $text): string
    {
        $text = mb_strtolower(trim($text));
        if ($text === '' || $this->containsDevanagari($text)) {
            return $text;
        }

        $digraphs = [
            'aa' => 'आ', 'ii' => 'ई', 'uu' => 'ऊ', 'ai' => 'ऐ', 'au' => 'औ',
            'chh' => 'छ', 'ch' => 'च', 'kh' => 'ख', 'gh' => 'घ',
            'ng' => 'ङ', 'ny' => 'ञ', 'th' => 'थ', 'dh' => 'ध',
            'ph' => 'फ', 'bh' => 'भ', 'sh' => 'श', 'ss' => 'ष',
            'tr' => 'त्र', 'gy' => 'ज्ञ', 'ksh' => 'क्ष',
        ];

        $singles = [
            'a' => 'अ', 'i' => 'इ', 'u' => 'उ', 'e' => 'ए', 'o' => 'ओ',
            'k' => 'क', 'g' => 'ग', 'j' => 'ज', 't' => 'त', 'd' => 'द',
            'n' => 'न', 'p' => 'प', 'b' => 'ब', 'm' => 'म', 'y' => 'य',
            'r' => 'र', 'l' => 'ल', 'w' => 'व', 'v' => 'व', 's' => 'स',
            'h' => 'ह', 'z' => 'ज', 'q' => 'क', 'x' => 'क्स',
        ];

        $out = '';
        $len = mb_strlen($text);
        $i = 0;
        while ($i < $len) {
            $two = mb_substr($text, $i, 2);
            $three = mb_substr($text, $i, 3);
            if (isset($digraphs[$three])) {
                $out .= $digraphs[$three];
                $i += 3;
            } elseif (isset($digraphs[$two])) {
                $out .= $digraphs[$two];
                $i += 2;
            } else {
                $one = mb_substr($text, $i, 1);
                $out .= $singles[$one] ?? $one;
                $i++;
            }
        }

        return $out;
    }
}
