<?php

namespace App\Jobs;

use App\Models\ModelTranslation;
use App\Services\Ai\GroqService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranslateContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public string $modelType;
    public int $modelId;
    public string $field;
    public int $tries = 3;

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

    public function handle(GroqService $groq): void
    {
        $class = self::MODEL_MAP[$this->modelType] ?? null;
        if (!$class) return;

        $item = $class::find($this->modelId);
        if (!$item) return;

        $text = $item->{$this->field};
        if (empty($text)) return;

        $exists = ModelTranslation::where('translatable_type', $class)
            ->where('translatable_id', $item->id)
            ->where('field', $this->field)
            ->where('locale', 'ne')
            ->exists();

        if ($exists) return;

        $translated = $groq->generate(
            "Translate the following text to Nepali language. Return ONLY the translated text, nothing else.\n\n{$text}"
        );

        ModelTranslation::create([
            'translatable_type' => $class,
            'translatable_id' => $item->id,
            'field' => $this->field,
            'locale' => 'ne',
            'translated_value' => $translated,
        ]);
    }
}
