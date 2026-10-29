<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\AdminSupportService;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSupportController extends Controller
{
    private const PER_PAGE_OPTIONS = [15, 20, 25, 50, 100];

    private const SORT_LABELS = [
        'priority' => 'Priority first',
        'newest' => 'Newest activity',
        'oldest' => 'Longest waiting',
        'created' => 'Newest created',
        'messages' => 'Most messages',
    ];

    public function __construct(
        private SupportService $supportService,
        private AdminSupportService $adminSupportService
    ) {}

    /**
     * Display the support inbox command center.
     */
    public function index(Request $request): View
    {
        $filters = $this->inboxFilters($request);
        $perPage = $this->perPage($request);
        $sort = $this->sort($request);

        $conversations = $this->supportService->getAdminInbox(
            status: $filters['status'],
            category: $filters['category'],
            priority: $filters['priority'],
            assignedTo: $filters['assigned_to'],
            search: $filters['search'],
            perPage: $perPage,
            sort: $sort,
        );

        $insights = $this->adminSupportService->insights();
        $stats = $insights['stats'];
        $staff = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['admin', 'super_admin', 'moderator']);
        })->orderBy('name')->get();

        $statusTabs = [
            ['key' => 'active', 'label' => 'Active', 'count' => $stats['active'], 'dot' => 'bg-primary-500'],
            ['key' => 'all', 'label' => 'All', 'count' => $stats['total'], 'dot' => 'bg-slate-400'],
            ['key' => 'open', 'label' => 'Open', 'count' => $stats['open'], 'dot' => 'bg-sky-500'],
            ['key' => 'ai_handling', 'label' => 'AI handling', 'count' => $stats['ai_handling'], 'dot' => 'bg-indigo-500'],
            ['key' => 'awaiting_human', 'label' => 'Awaiting human', 'count' => $stats['awaiting_human'], 'dot' => 'bg-amber-500'],
            ['key' => 'in_progress', 'label' => 'In progress', 'count' => $stats['in_progress'], 'dot' => 'bg-primary-600'],
            ['key' => 'resolved', 'label' => 'Resolved', 'count' => $stats['resolved'], 'dot' => 'bg-emerald-500'],
            ['key' => 'closed', 'label' => 'Closed', 'count' => $stats['closed'], 'dot' => 'bg-slate-400'],
        ];

        return view('admin.support_inbox', compact(
            'conversations',
            'insights',
            'stats',
            'staff',
            'filters',
            'perPage',
            'sort',
            'statusTabs',
        ) + [
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'sortLabels' => self::SORT_LABELS,
        ]);
    }

    /**
     * Show a single conversation with messages.
     */
    public function show(int $id): View
    {
        $conversation = $this->supportService->getConversation($id, 50);

        if (!$conversation) {
            abort(404);
        }

        $staff = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['admin', 'super_admin', 'moderator']);
        })->get();

        $totalMessages = $this->supportService->getMessageCount($id);
        $hasMoreMessages = $totalMessages > $conversation->messages->count();

        return view('admin.support_conversation', compact('conversation', 'staff', 'totalMessages', 'hasMoreMessages'));
    }

    /**
     * Reply to a conversation as staff.
     */
    public function reply(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'is_internal_note' => 'nullable|boolean',
        ]);

        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->staffReply(
            $conversation,
            $request->user(),
            $validated['message'],
            $validated['is_internal_note'] ?? false
        );

        return back()->with('success', 'Reply sent.');
    }

    /**
     * Assign a conversation to a staff member.
     */
    public function assign(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->reassign(
            $conversation,
            $validated['assigned_to'] ?? null,
            $request->user()->id
        );

        return back()->with('success', 'Conversation reassigned.');
    }

    /**
     * Take over a conversation.
     */
    public function takeOver(int $id): RedirectResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->takeOver($conversation, auth()->user());

        return back()->with('success', 'You have taken over this conversation.');
    }

    /**
     * Change conversation category.
     */
    public function changeCategory(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|in:' . implode(',', array_keys(SupportConversation::CATEGORIES)),
        ]);

        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->changeCategory(
            $conversation,
            $validated['category'],
            $request->user()->id
        );

        return back()->with('success', 'Category updated.');
    }

    /**
     * Change conversation priority.
     */
    public function changePriority(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'priority' => 'required|string|in:' . implode(',', array_keys(SupportConversation::PRIORITIES)),
        ]);

        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->changePriority(
            $conversation,
            $validated['priority'],
            $request->user()->id
        );

        return back()->with('success', 'Priority updated.');
    }

    /**
     * Resolve a conversation.
     */
    public function resolve(int $id): RedirectResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->resolve($conversation, auth()->user()->id);

        return back()->with('success', 'Conversation resolved.');
    }

    /**
     * Close a conversation.
     */
    public function close(int $id): RedirectResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->close($conversation, auth()->user()->id);

        return back()->with('success', 'Conversation closed.');
    }

    /**
     * Reopen a conversation.
     */
    public function reopen(int $id): RedirectResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->reopen($conversation, auth()->user()->id);

        return back()->with('success', 'Conversation reopened.');
    }

    /**
     * Escalate from AI to human.
     */
    public function escalate(int $id): RedirectResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->escalateToHuman($conversation, 'Manually escalated by staff.');

        return back()->with('success', 'Conversation escalated to human support.');
    }

    /**
     * Stop AI from responding.
     */
    public function stopAi(int $id): RedirectResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            abort(404);
        }

        $this->supportService->stopAiResponses($conversation);

        return back()->with('success', 'AI responses paused.');
    }

    /**
     * AJAX: Poll inbox for updated conversation list and stats.
     */
    public function pollInbox(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = $this->inboxFilters($request);
        $perPage = $this->perPage($request);
        $sort = $this->sort($request);

        $conversations = $this->supportService->getAdminInbox(
            status: $filters['status'],
            category: $filters['category'],
            priority: $filters['priority'],
            assignedTo: $filters['assigned_to'],
            search: $filters['search'],
            perPage: $perPage,
            sort: $sort,
        );

        $stats = $this->supportService->getInboxStats();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'rows_html' => view('admin.partials.support_queue_rows', compact('conversations'))->render(),
            'pagination_html' => (string) $conversations->withQueryString()->links(),
            'results_total' => $conversations->total(),
            'generated_at' => now()->format('M j, Y g:i A'),
        ]);
    }

    /**
     * @return array{status: ?string, category: ?string, priority: ?string, assigned_to: ?int, search: ?string}
     */
    private function inboxFilters(Request $request): array
    {
        $status = (string) $request->get('status', '');
        if ($status !== '' && $status !== 'all' && !array_key_exists($status, SupportConversation::STATUSES)) {
            $status = '';
        }

        $category = (string) $request->get('category', '');
        if ($category !== '' && !array_key_exists($category, SupportConversation::CATEGORIES)) {
            $category = '';
        }

        $priority = (string) $request->get('priority', '');
        if ($priority !== '' && !array_key_exists($priority, SupportConversation::PRIORITIES)) {
            $priority = '';
        }

        $assignedRaw = $request->get('assigned_to');
        $assignedTo = null;
        if ($assignedRaw === 'unassigned') {
            $assignedTo = 0;
        } elseif ($assignedRaw !== null && $assignedRaw !== '' && ctype_digit((string) $assignedRaw)) {
            $assignedTo = (int) $assignedRaw;
        }

        $search = trim((string) $request->get('search', ''));

        return [
            'status' => $status !== '' ? $status : null,
            'category' => $category !== '' ? $category : null,
            'priority' => $priority !== '' ? $priority : null,
            'assigned_to' => $assignedTo,
            'search' => $search !== '' ? $search : null,
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', 20);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 20;
    }

    private function sort(Request $request): string
    {
        $sort = (string) $request->get('sort', 'priority');

        return array_key_exists($sort, self::SORT_LABELS) ? $sort : 'priority';
    }

    /**
     * AJAX: Poll a single conversation for new messages, or load earlier messages.
     * Supports: since_id, since, before_message_id, limit
     */
    public function pollMessages(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $conversation = SupportConversation::find($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        $sinceId = (int) $request->get('since_id', 0);
        $sinceTimestamp = $request->get('since', null);
        $beforeMessageId = (int) $request->get('before_message_id', 0);
        $limit = min((int) $request->get('limit', 50), 100);

        $query = $conversation->messages()->with('user', 'editor');

        if ($beforeMessageId > 0) {
            $query->where('id', '<', $beforeMessageId)->orderBy('id', 'desc');
        } elseif ($sinceId > 0) {
            $query->where('id', '>', $sinceId)->orderBy('id');
        } elseif ($sinceTimestamp) {
            $query->where('created_at', '>', $sinceTimestamp)->orderBy('id');
        } else {
            $query->orderBy('id');
        }

        $messages = $query->limit($limit)->get();

        if ($beforeMessageId > 0) {
            $messages = $messages->reverse()->values();
        }

        $mappedMessages = $messages->map(fn($m) => [
            'id' => $m->id,
            'sender_type' => $m->sender_type,
            'content' => $m->content,
            'created_at' => $m->created_at->format('M d, g:ia'),
            'is_internal_note' => (bool) $m->is_internal_note,
            'ai_model' => $m->ai_model,
            'ai_confidence' => $m->ai_confidence,
            'ai_actions' => $m->ai_actions,
            'user_name' => match($m->sender_type) {
                'user' => $conversation->user->name ?? 'User',
                'ai' => 'AI Assistant',
                'system' => 'System',
                default => $m->user->name ?? 'Staff',
            },
            'avatar_letter' => match($m->sender_type) {
                'user' => strtoupper(substr($conversation->user->name ?? 'U', 0, 1)),
                'ai' => null,
                'system' => null,
                default => 'S',
            },
        ]);

        $hasMore = false;
        if ($beforeMessageId > 0 && $mappedMessages->isNotEmpty()) {
            $oldestId = $mappedMessages->first()['id'];
            $hasMore = SupportMessage::where('support_conversation_id', $id)
                ->where('id', '<', $oldestId)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'messages' => $mappedMessages,
            'status' => $conversation->status,
            'status_label' => $conversation->statusLabel(),
            'message_count' => $conversation->message_count,
            'has_more' => $hasMore,
        ]);
    }
}
