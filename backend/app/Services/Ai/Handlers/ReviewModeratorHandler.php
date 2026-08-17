<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\PlaceReview;
use App\Services\Rules\ReviewScoringService;
use Illuminate\Support\Facades\Log;

class ReviewModeratorHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'auto-moderate';

        if ($action === 'assess') {
            $count = PlaceReview::whereNull('moderated_at')->whereNot('rating', 0)->count();
            $msg = "{$count} review(s) pending moderation";
            return $this->markComplete($task, ['pending_reviews' => $count, 'message' => $msg]);
        }

        if (in_array($action, ['auto-moderate', 'auto'])) {
            return $this->handleAutoModerate($task);
        }

        if ($action === 'moderate' && isset($input['review_id'])) {
            return $this->moderateReview($task, (int) $input['review_id']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function handleAutoModerate(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results) . ' review(s) moderated (rules-based)';
        return $this->markComplete($task, ['moderated' => count($results), 'items' => $results, 'message' => $msg]);
    }

    protected function autoWork(): array
    {
        $results = [];

        $reviews = PlaceReview::whereNull('moderated_at')
            ->whereNot('rating', 0)
            ->orderBy('created_at')
            ->take(20)
            ->get();

        foreach ($reviews as $review) {
            try {
                $results[] = $this->applyVerdict($review);
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

        $result = $this->applyVerdict($review);

        return $this->markComplete($task, [
            'review_id' => $reviewId,
            'status' => $review->moderation_status,
            'score' => $result['score'],
            'reasons' => $result['reasons'],
        ]);
    }

    protected function applyVerdict(PlaceReview $review): array
    {
        $scored = app(ReviewScoringService::class)->score($review);

        // 'review' verdict: keep the review visible (moderation_status stays
        // null) but mark it moderated so it is not re-scanned every run —
        // the flag is recorded in the task output for human follow-up.
        $review->update([
            'moderated_at' => now(),
            'moderation_status' => match ($scored['verdict']) {
                'reject' => 'rejected',
                'approve' => 'approved',
                default => null,
            },
        ]);

        return [
            'review_id' => $review->id,
            'verdict' => $scored['verdict'],
            'score' => $scored['score'],
            'reasons' => $scored['reasons'],
        ];
    }
}
