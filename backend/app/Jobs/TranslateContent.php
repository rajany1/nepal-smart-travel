<?php

namespace App\Jobs;

use App\Exceptions\AiRateLimitException;
use App\Models\ModelTranslation;
use App\Services\Ai\AiProviderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranslateContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public string $modelType;
    public int $modelId;
    public string $field;
    public int $tries = 6;

    private const MODEL_MAP = [
        'place' => \App\Models\Place::class,
        'report' => \App\Models\Report::class,
        'place_review' => \App\Models\PlaceReview::class,
        'alert' => \App\Models\Alert::class,
    ];

    public function __construct(string $modelType, int $modelId, string $field)
    {
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->field = $field;
    }

    public function handle(AiProviderInterface $ai): void
    {
        $class = self::MODEL_MAP[$this->modelType] ?? null;
        if (!$class) return;

        $item = $class::find($this->modelId);
        if (!$item) return;

        $text = $item->{$this->field};
        if (empty($text)) return;

        $exists = ModelTranslation::where('translatable_type', $this->modelType)
            ->where('translatable_id', $item->id)
            ->where('field', $this->field)
            ->where('locale', 'ne')
            ->exists();

        if ($exists) return;

        try {
            $translated = $ai->generate(
                "Translate the following text to Nepali language. Return ONLY the translated text, nothing else.\n\n{$text}"
            );
        } catch (AiRateLimitException $e) {
            Log::warning('Translation paused: ' . $this->modelType . '#' . $this->modelId . ' field=' . $this->field . ': ' . $e->getMessage());

            if ($this->attempts() < $this->tries) {
                $this->release(600);
            } else {
                throw $e;
            }

            return;
        }

        ModelTranslation::create([
            'translatable_type' => $this->modelType,
            'translatable_id' => $item->id,
            'field' => $this->field,
            'locale' => 'ne',
            'source' => 'ai',
            'value' => $translated,
        ]);
    }
}
