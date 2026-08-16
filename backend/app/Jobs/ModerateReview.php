<?php

namespace App\Jobs;

use App\Exceptions\AiRateLimitException;
use App\Models\PlaceReview;
use App\Services\Ai\AiProviderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ModerateReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $reviewId;
    public int $tries = 6;

    public function __construct(int $reviewId)
    {
        $this->reviewId = $reviewId;
    }

    public function handle(AiProviderInterface $ai): void
    {
        $review = PlaceReview::find($this->reviewId);
        if (!$review || $review->moderated_at) return;

        $text = $review->description ?: $review->title ?: '';
        if (empty($text)) {
            $review->update(['moderated_at' => now(), 'moderation_status' => 'approved']);
            return;
        }

        try {
            $result = $ai->generateJson(
                "Moderate this place review. Return JSON: is_appropriate (bool — true if review is genuine, on-topic, and not spam), moderation_action (approve/reject/flag), reason (string, English, 1 sentence).\n\nReview: {$text}"
            );
        } catch (AiRateLimitException $e) {
            Log::warning('Review moderation paused for review#' . $this->reviewId . ': ' . $e->getMessage());

            if ($this->attempts() < $this->tries) {
                $this->release(600);
            } else {
                throw $e;
            }

            return;
        }

        $action = $result['moderation_action'] ?? 'approve';
        $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'flagged');

        $review->update([
            'moderation_status' => $status,
            'moderated_at' => now(),
        ]);
    }
}
