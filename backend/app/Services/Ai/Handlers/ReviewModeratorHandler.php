<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\PlaceReview;
use Illuminate\Support\Facades\Log;

class ReviewModeratorHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'auto-moderate';

        if (in_array($action, ['auto-moderate', 'auto'])) {
            return $this->handleAutoModerate($task);
        }

        if ($action === 'moderate' && isset($input['review_id'])) {
            return $this->moderateReview($task, $input['review_id']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function handleAutoModerate(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results) . ' review(s) moderated';
        return $this->markComplete($task, ['moderated' => count($results), 'items' => $results, 'message' => $msg]);
    }

    protected function autoWork(): array
    {
        $llm = $this->ai();
        $results = [];

        $reviews = PlaceReview::whereNull('moderated_at')
            ->whereNot('rating', 0)
            ->inRandomOrder()
            ->take(10)
            ->get();

        foreach ($reviews as $review) {
            try {
                $text = $review->description ?: $review->title ?: '';
                if (empty($text)) {
                    $review->update(['moderated_at' => now(), 'moderation_status' => 'approved']);
                    $results[] = "review#{$review->id}: approved (empty)";
                    continue;
                }

                $result = $llm->generateJson(
                    "Analyze this place review and return JSON with: spam (bool), toxic (bool), reason (string).\n\nReview: {$text}"
                );

                $isSpam = $result['spam'] ?? false;
                $isToxic = $result['toxic'] ?? false;

                if ($isSpam || $isToxic) {
                    $review->update([
                        'moderated_at' => now(),
                        'moderation_status' => 'rejected',
                    ]);
                    $results[] = "review#{$review->id}: rejected (" . ($result['reason'] ?? 'spam/toxic') . ")";
                } else {
                    $review->update([
                        'moderated_at' => now(),
                        'moderation_status' => 'approved',
                    ]);
                    $results[] = "review#{$review->id}: approved";
                }
            } catch (\Exception $e) {
                Log::error("Review moderation failed for review#{$review->id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    protected function moderateReview(AiAgentTask $task, int $reviewId): AiAgentTask
    {
        $review = PlaceReview::find($reviewId);
        if (!$review) {
            return $this->markFailed($task, "Review #{$reviewId} not found");
        }

        $llm = $this->ai();
        $text = $review->description ?: $review->title ?: '';

        try {
            $result = $llm->generateJson("Analyze this review: spam/toxicity check. Return JSON with spam, toxic, reason.\n\n{$text}");

            $review->update([
                'moderated_at' => now(),
                'moderation_status' => ($result['spam'] ?? false) || ($result['toxic'] ?? false) ? 'rejected' : 'approved',
            ]);

            return $this->markComplete($task, [
                'review_id' => $reviewId,
                'status' => $review->moderation_status,
                'reason' => $result['reason'] ?? '',
            ]);
        } catch (\Exception $e) {
            return $this->markFailed($task, $e->getMessage());
        }
    }
}
