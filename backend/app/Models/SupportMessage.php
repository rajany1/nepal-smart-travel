<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_conversation_id',
        'user_id',
        'sender_type',
        'content',
        'metadata',
        'ai_model',
        'ai_confidence',
        'ai_actions',
        'is_internal_note',
        'edited_at',
        'edited_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'ai_confidence' => 'decimal:2',
        'ai_actions' => 'array',
        'is_internal_note' => 'boolean',
        'edited_at' => 'datetime',
    ];

    // --- Sender Types ---

    const SENDER_USER = 'user';
    const SENDER_AI = 'ai';
    const SENDER_SUPPORT = 'support';
    const SENDER_SYSTEM = 'system';

    const SENDER_TYPES = [
        self::SENDER_USER => 'User',
        self::SENDER_AI => 'AI',
        self::SENDER_SUPPORT => 'Support Staff',
        self::SENDER_SYSTEM => 'System',
    ];

    // --- Relationships ---

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    // --- Scopes ---

    public function scopeNonInternal($query)
    {
        return $query->where('is_internal_note', false);
    }

    public function scopeBySender($query, string $senderType)
    {
        return $query->where('sender_type', $senderType);
    }

    // --- Helpers ---

    public function isFromUser(): bool
    {
        return $this->sender_type === self::SENDER_USER;
    }

    public function isFromAi(): bool
    {
        return $this->sender_type === self::SENDER_AI;
    }

    public function isFromSupport(): bool
    {
        return $this->sender_type === self::SENDER_SUPPORT;
    }

    public function isFromSystem(): bool
    {
        return $this->sender_type === self::SENDER_SYSTEM;
    }

    public function senderTypeLabel(): string
    {
        return self::SENDER_TYPES[$this->sender_type] ?? $this->sender_type;
    }
}
