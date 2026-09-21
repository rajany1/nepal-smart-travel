<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject',
        'category',
        'priority',
        'status',
        'assigned_to',
        'ai_handled',
        'ai_confidence',
        'last_reply_at',
        'last_reply_by',
        'message_count',
        'metadata',
    ];

    protected $casts = [
        'ai_handled' => 'boolean',
        'ai_confidence' => 'decimal:2',
        'message_count' => 'integer',
        'metadata' => 'array',
        'last_reply_at' => 'datetime',
    ];

    // --- Constants ---

    const CATEGORY_GENERAL = 'general';
    const CATEGORY_ACCOUNT = 'account';
    const CATEGORY_PAYMENT = 'payment';
    const CATEGORY_WALLET = 'wallet';
    const CATEGORY_BOOKING = 'booking';
    const CATEGORY_REPORT = 'report';
    const CATEGORY_ABUSE = 'abuse';
    const CATEGORY_MODERATION = 'moderation';
    const CATEGORY_BUG = 'bug';
    const CATEGORY_FEATURE_REQUEST = 'feature_request';
    const CATEGORY_OTHER = 'other';

    const CATEGORIES = [
        self::CATEGORY_GENERAL => 'General',
        self::CATEGORY_ACCOUNT => 'Account',
        self::CATEGORY_PAYMENT => 'Payment',
        self::CATEGORY_WALLET => 'Wallet',
        self::CATEGORY_BOOKING => 'Booking',
        self::CATEGORY_REPORT => 'Report',
        self::CATEGORY_ABUSE => 'Abuse',
        self::CATEGORY_MODERATION => 'Moderation',
        self::CATEGORY_BUG => 'Bug Report',
        self::CATEGORY_FEATURE_REQUEST => 'Feature Request',
        self::CATEGORY_OTHER => 'Other',
    ];

    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    const PRIORITIES = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_NORMAL => 'Normal',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_AI_HANDLING = 'ai_handling';
    const STATUS_AWAITING_HUMAN = 'awaiting_human';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_AI_HANDLING => 'AI Handling',
        self::STATUS_AWAITING_HUMAN => 'Awaiting Human',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_RESOLVED => 'Resolved',
        self::STATUS_CLOSED => 'Closed',
    ];

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function lastReplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reply_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'support_conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class, 'support_conversation_id')->latestOfMany();
    }

    public function satisfaction(): HasOne
    {
        return $this->hasOne(SupportSatisfaction::class, 'support_conversation_id');
    }

    // --- Scopes ---

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_AI_HANDLING,
            self::STATUS_AWAITING_HUMAN,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeForCategory($query, ?string $category)
    {
        if ($category) {
            return $query->where('category', $category);
        }
        return $query;
    }

    public function scopeForPriority($query, ?string $priority)
    {
        if ($priority) {
            return $query->where('priority', $priority);
        }
        return $query;
    }

    public function scopeForStatus($query, ?string $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }

    // --- Helpers ---

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_AI_HANDLING,
            self::STATUS_AWAITING_HUMAN,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    public function canUserReply(): bool
    {
        return $this->status !== self::STATUS_CLOSED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function isAiHandling(): bool
    {
        return $this->status === self::STATUS_AI_HANDLING;
    }

    public function isAwaitingHuman(): bool
    {
        return $this->status === self::STATUS_AWAITING_HUMAN;
    }
}
