<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\ModelTranslation;
use App\Models\Place;
use App\Services\PlaceDescriptionBuilder;
use Illuminate\Support\Facades\Log;

class ContentWriterHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'auto';

        if ($action === 'assess') {
            return $this->assess($task);
        }

        if (in_array($action, ['auto', 'auto-work'])) {
            return $this->handleAutoWork($task);
        }

        if ($action === 'write' && isset($input['place_id'])) {
            return $this->writeDescription($task, (int) $input['place_id']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function assess(AiAgentTask $task): AiAgentTask
    {
        $count = $this->placesMissingDescription()->count();
        $msg = "{$count} place(s) missing a description — Nepali copy can be written for them";
        return $this->markComplete($task, ['places_missing_description' => $count, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results) . ' description(s) written and stored (templates)';
        return $this->markComplete($task, ['written' => count($results), 'items' => $results, 'message' => $msg]);
    }

    protected function autoWork(): array
    {
        $builder = app(PlaceDescriptionBuilder::class);
        $results = [];

        $places = $this->placesMissingDescription()->take(10)->get();

        foreach ($places as $place) {
            try {
                $exists = ModelTranslation::where('translatable_type', 'place')
                    ->where('translatable_id', $place->id)
                    ->where('locale', 'ne')
                    ->where('field', 'description')
                    ->exists();

                if ($exists) {
                    continue;
                }

                $content = $builder->build($place);

                ModelTranslation::create([
                    'translatable_type' => 'place',
                    'translatable_id' => $place->id,
                    'locale' => 'ne',
                    'field' => 'description',
                    'value' => $content,
                    'source' => 'rules',
                ]);

                $results[] = "place#{$place->id} ({$place->name})";
            } catch (\Exception $e) {
                Log::error("Content writer failed for place#{$place->id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    protected function writeDescription(AiAgentTask $task, int $placeId): AiAgentTask
    {
        $place = Place::find($placeId);
        if (!$place) {
            return $this->markFailed($task, "Place #{$placeId} not found");
        }

        $content = app(PlaceDescriptionBuilder::class)->build($place);

        ModelTranslation::updateOrCreate(
            [
                'translatable_type' => 'place',
                'translatable_id' => $place->id,
                'locale' => 'ne',
                'field' => 'description',
            ],
            ['value' => $content, 'source' => 'rules']
        );

        return $this->markComplete($task, [
            'place_id' => $placeId,
            'place' => $place->name,
            'description_written' => $content,
        ]);
    }

    protected function placesMissingDescription()
    {
        return Place::active()
            ->where(function ($q) {
                $q->whereNull('description')
                    ->orWhere('description', '')
                    ->orWhereRaw("CHAR_LENGTH(description) < 30");
            })
            ->whereNotNull('name');
    }
}
