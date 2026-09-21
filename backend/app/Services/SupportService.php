<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\Ai\AgentOrchestrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SupportService
{
    /**
     * Create a new support conversation with an initial user message.
     */
    public function createConversation(
        User $user,
        string $subject,
        string $message,
        string $category = SupportConversation::CATEGORY_GENERAL,
        string $priority = SupportConversation::PRIORITY_NORMAL,
        array $metadata = null
    ): SupportConversation {
        return DB::transaction(function () use ($user, $subject, $message, $category, $priority, $metadata) {
            $conversation = SupportConversation::create([
                'user_id' => $user->id,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => SupportConversation::STATUS_OPEN,
                'message_count' => 1,
                'last_reply_at' => now(),
                'last_reply_by' => $user->id,
                'metadata' => $metadata,
            ]);

            SupportMessage::create([
                'support_conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'sender_type' => SupportMessage::SENDER_USER,
                'content' => $message,
            ]);

            return $conversation->fresh(['latestMessage', 'user']);
        });
    }

    /**
     * Add a message to an existing conversation.
     */
    public function addMessage(
        SupportConversation $conversation,
        ?int $userId,
        string $senderType,
        string $content,
        array $metadata = null,
        bool $isInternalNote = false,
        string $aiModel = null,
        float $aiConfidence = null,
        array $aiActions = null
    ): SupportMessage {
        return DB::transaction(function () use ($conversation, $userId, $senderType, $content, $metadata, $isInternalNote, $aiModel, $aiConfidence, $aiActions) {
            $message = SupportMessage::create([
                'support_conversation_id' => $conversation->id,
                'user_id' => $userId,
                'sender_type' => $senderType,
                'content' => $content,
                'metadata' => $metadata,
                'is_internal_note' => $isInternalNote,
                'ai_model' => $aiModel,
                'ai_confidence' => $aiConfidence,
                'ai_actions' => $aiActions,
            ]);

            $conversation->update([
                'message_count' => $conversation->message_count + 1,
                'last_reply_at' => now(),
                'last_reply_by' => $userId,
            ]);

            return $message;
        });
    }

    /**
     * Add a system event message to a conversation.
     */
    public function addSystemEvent(
        SupportConversation $conversation,
        string $eventDescription,
        array $metadata = null
    ): SupportMessage {
        return $this->addMessage(
            $conversation,
            null,
            SupportMessage::SENDER_SYSTEM,
            $eventDescription,
            $metadata
        );
    }

    /**
     * User replies to their conversation.
     */
    public function userReply(
        SupportConversation $conversation,
        User $user,
        string $content
    ): SupportMessage {
        if ($conversation->user_id !== $user->id) {
            throw new \InvalidArgumentException('User does not own this conversation.');
        }

        if (!$conversation->canUserReply()) {
            throw new \InvalidArgumentException('Cannot reply to a closed conversation.');
        }

        // If conversation was resolved/closed, reopen it
        if (in_array($conversation->status, [
            SupportConversation::STATUS_RESOLVED,
            SupportConversation::STATUS_CLOSED,
        ])) {
            $conversation->update(['status' => SupportConversation::STATUS_OPEN]);
            $this->addSystemEvent($conversation, 'Conversation reopened by user.');
        }

        // NOTE: Status transition from ai_handling is NOT done here.
        // The controller checks isAiEligible() after this call and decides
        // whether to dispatch AI. If AI is dispatched, status stays ai_handling.
        // If AI is not eligible (e.g. human took over), status is already correct.

        return $this->addMessage(
            $conversation,
            $user->id,
            SupportMessage::SENDER_USER,
            $content
        );
    }

    /**
     * Support staff replies to a conversation.
     */
    public function staffReply(
        SupportConversation $conversation,
        User $staff,
        string $content,
        bool $isInternalNote = false
    ): SupportMessage {
        if (!$staff->isModerator() && !$staff->isAdmin()) {
            throw new \InvalidArgumentException('User is not support staff.');
        }

        // Auto-assign if not yet assigned
        if (!$conversation->assigned_to) {
            $conversation->update([
                'assigned_to' => $staff->id,
                'status' => SupportConversation::STATUS_IN_PROGRESS,
            ]);
        }

        // If status was awaiting_human or open, move to in_progress
        if (in_array($conversation->status, [
            SupportConversation::STATUS_OPEN,
            SupportConversation::STATUS_AWAITING_HUMAN,
        ])) {
            $conversation->update(['status' => SupportConversation::STATUS_IN_PROGRESS]);
        }

        return $this->addMessage(
            $conversation,
            $staff->id,
            SupportMessage::SENDER_SUPPORT,
            $content,
            null,
            $isInternalNote
        );
    }

    /**
     * AI adds a response to the conversation.
     */
    public function aiReply(
        SupportConversation $conversation,
        string $content,
        string $aiModel = null,
        float $aiConfidence = null,
        array $aiActions = null,
        ?int $userId = null
    ): SupportMessage {
        // Update AI handling status
        if (in_array($conversation->status, [
            SupportConversation::STATUS_OPEN,
            SupportConversation::STATUS_AI_HANDLING,
        ])) {
            $conversation->update([
                'status' => SupportConversation::STATUS_AI_HANDLING,
                'ai_handled' => true,
                'ai_confidence' => $aiConfidence,
            ]);
        }

        return $this->addMessage(
            $conversation,
            $userId,
            SupportMessage::SENDER_AI,
            $content,
            null,
            false,
            $aiModel,
            $aiConfidence,
            $aiActions
        );
    }

    /**
     * Escalate a conversation from AI to human support.
     */
    public function escalateToHuman(
        SupportConversation $conversation,
        string $reason = 'Escalated to human support'
    ): SupportConversation {
        $conversation->update([
            'status' => SupportConversation::STATUS_AWAITING_HUMAN,
            'assigned_to' => null,
        ]);

        $this->addSystemEvent($conversation, "Escalated to human support: {$reason}");

        return $conversation;
    }

    /**
     * Take over a conversation (staff assigns to self).
     */
    public function takeOver(
        SupportConversation $conversation,
        User $staff
    ): SupportConversation {
        if (!$staff->isModerator() && !$staff->isAdmin()) {
            throw new \InvalidArgumentException('User is not support staff.');
        }

        $conversation->update([
            'assigned_to' => $staff->id,
            'status' => SupportConversation::STATUS_IN_PROGRESS,
        ]);

        $this->addSystemEvent(
            $conversation,
            "Conversation taken over by {$staff->name}."
        );

        return $conversation;
    }

    /**
     * Reassign a conversation to another staff member.
     */
    public function reassign(
        SupportConversation $conversation,
        ?int $newAssigneeId,
        ?int $reassignedBy
    ): SupportConversation {
        $conversation->update(['assigned_to' => $newAssigneeId]);

        $assigneeName = $newAssigneeId ? User::find($newAssigneeId)?->name : 'unassigned';
        $this->addSystemEvent(
            $conversation,
            "Conversation reassigned to {$assigneeName}."
        );

        return $conversation;
    }

    /**
     * Change conversation category.
     */
    public function changeCategory(
        SupportConversation $conversation,
        string $newCategory,
        ?int $changedBy
    ): SupportConversation {
        $old = $conversation->category;
        $conversation->update(['category' => $newCategory]);
        $this->addSystemEvent(
            $conversation,
            "Category changed from \"{$old}\" to \"{$newCategory}\"."
        );
        return $conversation;
    }

    /**
     * Change conversation priority.
     */
    public function changePriority(
        SupportConversation $conversation,
        string $newPriority,
        ?int $changedBy
    ): SupportConversation {
        $old = $conversation->priority;
        $conversation->update(['priority' => $newPriority]);
        $this->addSystemEvent(
            $conversation,
            "Priority changed from \"{$old}\" to \"{$newPriority}\"."
        );
        return $conversation;
    }

    /**
     * Resolve a conversation.
     */
    public function resolve(
        SupportConversation $conversation,
        ?int $resolvedBy
    ): SupportConversation {
        $conversation->update(['status' => SupportConversation::STATUS_RESOLVED]);
        $this->addSystemEvent($conversation, 'Conversation resolved.');
        return $conversation;
    }

    /**
     * Close a conversation.
     */
    public function close(
        SupportConversation $conversation,
        ?int $closedBy
    ): SupportConversation {
        $conversation->update(['status' => SupportConversation::STATUS_CLOSED]);
        $this->addSystemEvent($conversation, 'Conversation closed.');
        return $conversation;
    }

    /**
     * Reopen a resolved/closed conversation.
     */
    public function reopen(
        SupportConversation $conversation,
        ?int $reopenedBy
    ): SupportConversation {
        $conversation->update(['status' => SupportConversation::STATUS_OPEN]);
        $this->addSystemEvent($conversation, 'Conversation reopened.');
        return $conversation;
    }

    /**
     * Get conversation with relations for display.
     * Supports message pagination: returns latest N messages by default.
     * Pass 'oldest' message ID to load earlier messages.
     */
    public function getConversation(
        int $conversationId,
        int $messagesPerPage = 50,
        ?int $beforeMessageId = null
    ): ?SupportConversation {
        $conversation = SupportConversation::with([
            'user',
            'assignee',
            'lastReplier',
        ])->find($conversationId);

        if (!$conversation) {
            return null;
        }

        $messagesQuery = $conversation->messages()->with('user', 'editor');

        if ($beforeMessageId) {
            $messagesQuery->where('id', '<', $beforeMessageId);
        }

        $messages = $messagesQuery->orderBy('id', 'desc')
            ->limit($messagesPerPage)
            ->get()
            ->reverse()
            ->values();

        $conversation->setRelation('messages', $messages);

        return $conversation;
    }

    /**
     * Get total message count for a conversation.
     */
    public function getMessageCount(int $conversationId): int
    {
        return SupportMessage::where('support_conversation_id', $conversationId)->count();
    }

    /**
     * Get paginated conversations for admin inbox.
     */
    public function getAdminInbox(
        ?string $status = null,
        ?string $category = null,
        ?string $priority = null,
        ?int $assignedTo = null,
        ?string $search = null,
        int $perPage = 20
    ) {
        $query = SupportConversation::with(['user', 'assignee', 'latestMessage.user']);

        // If filtering by specific status, use that; otherwise default to open statuses
        if ($status) {
            $query->where('status', $status);
        } else {
            $query->open();
        }

        if ($category) {
            $query->where('category', $category);
        }
        if ($priority) {
            $query->where('priority', $priority);
        }
        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderByRaw("CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
            WHEN 'low' THEN 4
        END")
        ->orderBy('last_reply_at', 'desc')
        ->paginate($perPage);
    }

    /**
     * Get inbox statistics for admin dashboard.
     */
    public function getInboxStats(): array
    {
        $all = SupportConversation::count();
        $open = SupportConversation::where('status', SupportConversation::STATUS_OPEN)->count();
        $aiHandling = SupportConversation::where('status', SupportConversation::STATUS_AI_HANDLING)->count();
        $awaitingHuman = SupportConversation::where('status', SupportConversation::STATUS_AWAITING_HUMAN)->count();
        $inProgress = SupportConversation::where('status', SupportConversation::STATUS_IN_PROGRESS)->count();
        $resolved = SupportConversation::where('status', SupportConversation::STATUS_RESOLVED)->count();
        $closed = SupportConversation::where('status', SupportConversation::STATUS_CLOSED)->count();
        $unassigned = SupportConversation::open()->whereNull('assigned_to')->count();

        $reviewedCount = \App\Models\SupportSatisfaction::count();
        $avgRating = \App\Models\SupportSatisfaction::avg('rating');
        $ratingDistribution = \App\Models\SupportSatisfaction::selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return [
            'total' => $all,
            'open' => $open,
            'ai_handling' => $aiHandling,
            'awaiting_human' => $awaitingHuman,
            'in_progress' => $inProgress,
            'resolved' => $resolved,
            'closed' => $closed,
            'unassigned' => $unassigned,
            'reviewed' => $reviewedCount,
            'avg_rating' => $avgRating ? round($avgRating, 1) : null,
            'rating_distribution' => $ratingDistribution,
        ];
    }

    /**
     * Check if a user has access to a conversation.
     */
    public function userHasAccess(User $user, SupportConversation $conversation): bool
    {
        // Owner always has access
        if ($conversation->user_id === $user->id) {
            return true;
        }

        // Admin/moderator always has access
        if ($user->isAdmin() || $user->isModerator()) {
            return true;
        }

        return false;
    }

    /**
     * Stop AI from responding (human takeover or escalation).
     */
    public function stopAiResponses(SupportConversation $conversation): SupportConversation
    {
        if ($conversation->status === SupportConversation::STATUS_AI_HANDLING) {
            $conversation->update(['status' => SupportConversation::STATUS_AWAITING_HUMAN]);
            $this->addSystemEvent($conversation, 'AI responses paused. Awaiting human support.');
        }
        return $conversation;
    }

    /**
     * Check whether AI is eligible to respond to this conversation.
     *
     * AI is eligible when:
     * - Status is 'open' (new conversation, AI hasn't started yet)
     * - Status is 'ai_handling' (AI is already handling)
     *
     * AI is NOT eligible when:
     * - Status is 'awaiting_human' (explicitly escalated / user replied after AI)
     * - Status is 'in_progress' (staff is handling)
     * - Status is 'resolved' or 'closed'
     */
    public function isAiEligible(SupportConversation $conversation): bool
    {
        return in_array($conversation->status, [
            SupportConversation::STATUS_OPEN,
            SupportConversation::STATUS_AI_HANDLING,
        ]);
    }

    /**
     * Dispatch an AI reply for a support conversation.
     *
     * This creates an AiAgentTask targeting the customer_support agent
     * with the existing support_conversation_id so AI appends to the
     * same conversation instead of creating a new one.
     *
     * Returns the task if successfully dispatched, null otherwise.
     */
    public function dispatchAiReply(
        SupportConversation $conversation,
        string $message,
        ?float $lat = null,
        ?float $lng = null
    ): ?AiAgentTask {
        if (!$this->isAiEligible($conversation)) {
            return null;
        }

        $agent = AiAgent::where('agent_type', 'customer_support')
            ->where('status', '!=', 'paused')
            ->first();

        if (!$agent) {
            Log::warning('No active customer_support AI agent found for support conversation #' . $conversation->id);
            return null;
        }

        try {
            $task = AiAgentTask::create([
                'ai_agent_id' => $agent->id,
                'type' => 'chat',
                'status' => 'pending',
                'input_data' => [
                    'action' => 'chat',
                    'message' => $message,
                    'lat' => $lat,
                    'lng' => $lng,
                    'user_id' => $conversation->user_id,
                    'support_conversation_id' => $conversation->id,
                ],
            ]);

            $orchestrator = app(AgentOrchestrator::class);
            $orchestrator->executeTask($task);

            return $task->fresh();
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch AI reply for support conversation #' . $conversation->id . ': ' . $e->getMessage());
            return null;
        }
    }
}
