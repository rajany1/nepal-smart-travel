<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\ModelTranslation;
use App\Models\Place;
use App\Models\Alert;
use App\Models\Report;
use App\Models\PlaceReview;
use App\Services\GlossaryTranslator;
use Illuminate\Support\Facades\Log;

class TranslationHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $type = $input['type'] ?? 'auto';

        if ($type === 'assess') {
            $count = 0;
            foreach ($this->getTranslationSources() as $source) {
                $count += $source['query']->count();
            }
            $msg = "{$count} untranslated item(s) found across place/review/report/alert content";
            return $this->markComplete($task, ['untranslated_items' => $count, 'message' => $msg]);
        }

        if ($type === 'auto') {
            return $this->handleAutoWork($task);
        }

        return $this->markFailed($task, 'Unknown translation type: ' . $type);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results) . ' item(s) translated to Nepali (glossary)';
        return $this->markComplete($task, ['translated' => count($results), 'items' => $results, 'message' => $msg]);
    }

    protected function autoWork(): array
    {
        $translator = app(GlossaryTranslator::class);
        $results = [];

        $sources = $this->getTranslationSources();

        foreach ($sources as $source) {
            $items = $source['query']->get();

            foreach ($items as $item) {
                $field = $source['field'];
                $text = trim((string) $item->$field);

                if ($text === '') {
                    continue;
                }

                $exists = ModelTranslation::where('translatable_type', $source['type'])
                    ->where('translatable_id', $item->id)
                    ->where('locale', 'ne')
                    ->where('field', $field)
                    ->exists();

                if ($exists) {
                    continue;
                }

                try {
                    if ($field === 'name' || $translator->containsDevanagari($text)) {
                        // Names: glossary hit first, else best-effort
                        // transliteration (only for non-Devanagari input).
                        $translated = $this->translateName($translator, $text);
                        if ($translated === null) {
                            continue;
                        }
                    } else {
                        // Free text: glossary term replacement; text that is
                        // already Nepali stays untouched.
                        $translated = $translator->translateText($text)['text'];
                        if ($translated === $text) {
                            continue;
                        }
                    }

                    ModelTranslation::create([
                        'translatable_type' => $source['type'],
                        'translatable_id' => $item->id,
                        'locale' => 'ne',
                        'field' => $field,
                        'value' => $translated,
                        'source' => 'rules',
                    ]);

                    $results[] = "{$source['type']}#{$item->id}.{$field}";
                } catch (\Exception $e) {
                    Log::error("Translation failed for {$source['type']}#{$item->id}.{$field}: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    protected function translateName(GlossaryTranslator $translator, string $text): ?string
    {
        if ($translator->containsDevanagari($text)) {
            return null;
        }

        $glossed = $translator->translateText($text);
        if ($glossed['text'] !== $text) {
            return $glossed['text'];
        }

        $transliterated = $translator->transliterate($text);
        return $transliterated !== mb_strtolower(trim($text)) ? $transliterated : null;
    }

    protected function getTranslationSources(): array
    {
        return [
            [
                'type' => 'place',
                'field' => 'description',
                'lang_hint' => 'English',
                'query' => Place::whereNotNull('description')
                    ->where('description', '!=', '')
                    ->inRandomOrder()
                    ->take(10),
            ],
            [
                'type' => 'place',
                'field' => 'name',
                'lang_hint' => 'English',
                'query' => Place::inRandomOrder()->take(10),
            ],
            [
                'type' => 'place_review',
                'field' => 'description',
                'lang_hint' => 'English',
                'query' => PlaceReview::whereNotNull('description')
                    ->where('description', '!=', '')
                    ->inRandomOrder()
                    ->take(10),
            ],
            [
                'type' => 'report',
                'field' => 'title',
                'lang_hint' => 'English',
                'query' => Report::whereNotNull('title')
                    ->where('title', '!=', '')
                    ->inRandomOrder()
                    ->take(10),
            ],
            [
                'type' => 'report',
                'field' => 'description',
                'lang_hint' => 'English',
                'query' => Report::whereNotNull('description')
                    ->where('description', '!=', '')
                    ->inRandomOrder()
                    ->take(10),
            ],
            [
                'type' => 'alert',
                'field' => 'description',
                'lang_hint' => 'English',
                'query' => Alert::whereNotNull('description')
                    ->where('description', '!=', '')
                    ->inRandomOrder()
                    ->take(10),
            ],
        ];
    }
}
