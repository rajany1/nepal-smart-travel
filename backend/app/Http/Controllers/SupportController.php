<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportSatisfaction;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function __construct(
        private SupportService $supportService
    ) {}

    /**
     * List the current user's support conversations.
     * Excludes conversations that the user has already rated (hidden after review).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = SupportConversation::where('user_id', $user->id)
            ->with(['latestMessage.user'])
            ->whereDoesntHave('satisfaction') // Hide rated conversations
            ->orderBy('last_reply_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Create a new support conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'category' => 'nullable|string|in:' . implode(',', array_keys(SupportConversation::CATEGORIES)),
            'priority' => 'nullable|string|in:' . implode(',', array_keys(SupportConversation::PRIORITIES)),
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $conversation = $this->supportService->createConversation(
            $request->user(),
            $validated['subject'],
            $validated['message'],
            $validated['category'] ?? SupportConversation::CATEGORY_GENERAL,
            $validated['priority'] ?? SupportConversation::PRIORITY_NORMAL,
        );

        // Trigger AI first-line response (non-blocking, best-effort)
        if ($this->supportService->isAiEligible($conversation)) {
            try {
                $this->supportService->dispatchAiReply(
                    $conversation,
                    $validated['message'],
                    $validated['lat'] ?? null,
                    $validated['lng'] ?? null
                );
            } catch (\Throwable $e) {
                Log::warning('AI dispatch failed for new conversation #' . $conversation->id . ': ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ], 201);
    }

    /**
     * Get a specific conversation with messages.
     * Supports:
     *   ?since= timestamp to poll for new messages only
     *   ?before_message_id= to load earlier messages
     *   ?per_page= messages per page (default 50)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 100);
        $beforeMessageId = $request->query('before_message_id') ? (int) $request->query('before_message_id') : null;

        $conversation = $this->supportService->getConversation($id, $perPage, $beforeMessageId);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        }

        if (!$this->supportService->userHasAccess($request->user(), $conversation)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Filter out internal notes for non-staff users
        $isStaff = $request->user()->isAdmin() || $request->user()->isModerator();
        if (!$isStaff && $conversation->relationLoaded('messages')) {
            $conversation->setRelation(
                'messages',
                $conversation->messages->reject(fn($m) => $m->is_internal_note)->values()
            );
        }

        // If polling with ?since=, only return new messages
        $since = $request->query('since');
        if ($since && $conversation->relationLoaded('messages')) {
            $conversation->setRelation(
                'messages',
                $conversation->messages->filter(fn($m) => $m->created_at->timestamp > (int) $since)->values()
            );
        }

        // Message pagination metadata
        $totalMessages = $this->supportService->getMessageCount($id);
        $loadedMessages = $conversation->messages->count();
        $oldestLoadedId = $conversation->messages->isNotEmpty() ? $conversation->messages->first()->id : null;
        $hasMoreMessages = $oldestLoadedId
            ? SupportMessage::where('support_conversation_id', $id)->where('id', '<', $oldestLoadedId)->exists()
            : false;

        return response()->json([
            'success' => true,
            'data' => $conversation,
            'messages' => [
                'total' => $totalMessages,
                'loaded' => $loadedMessages,
                'has_more' => $hasMoreMessages,
                'oldest_id' => $oldestLoadedId,
            ],
            'polled_at' => now()->timestamp,
        ]);
    }

    /**
     * Reply to a conversation.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $conversation = SupportConversation::find($id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        }

        if (!$this->supportService->userHasAccess($request->user(), $conversation)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        try {
            $message = $this->supportService->userReply(
                $conversation,
                $request->user(),
                $validated['message']
            );

            // Reload conversation to get fresh status after userReply
            $conversation->refresh();

            // Trigger AI first-line response if eligible (non-blocking, best-effort)
            // The user message is already persisted at this point.
            if ($this->supportService->isAiEligible($conversation)) {
                try {
                    $this->supportService->dispatchAiReply(
                        $conversation,
                        $validated['message'],
                        $validated['lat'] ?? null,
                        $validated['lng'] ?? null
                    );
                } catch (\Throwable $e) {
                    Log::warning('AI dispatch failed for conversation #' . $id . ': ' . $e->getMessage());
                }
            } elseif ($conversation->status === SupportConversation::STATUS_IN_PROGRESS) {
                // Human is handling but user sent a new message — escalate to awaiting_human
                $this->supportService->escalateToHuman($conversation, 'User replied while staff was handling.');
            }

            return response()->json([
                'success' => true,
                'data' => $message->fresh('user'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get categories and priorities for form options.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => SupportConversation::CATEGORIES,
                'priorities' => SupportConversation::PRIORITIES,
                'statuses' => SupportConversation::STATUSES,
            ],
        ]);
    }

    /**
     * Check if a conversation has a satisfaction review.
     */
    public function satisfaction(Request $request, int $id): JsonResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation || $conversation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $review = $conversation->satisfaction;

        return response()->json([
            'success' => true,
            'data' => $review ? [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'submitted_at' => $review->created_at->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Submit a satisfaction review for a resolved/closed conversation.
     */
    public function submitSatisfaction(Request $request, int $id): JsonResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation || $conversation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        if (!in_array($conversation->status, ['resolved', 'closed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews can only be submitted for resolved or closed conversations.',
            ], 422);
        }

        if ($conversation->satisfaction) {
            return response()->json([
                'success' => false,
                'message' => 'A review has already been submitted for this conversation.',
            ], 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = SupportSatisfaction::create([
            'support_conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'submitted_at' => $review->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
