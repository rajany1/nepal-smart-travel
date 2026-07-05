<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationQueue extends Model
{
    protected $fillable = [
        'content_type',
        'content_id',
        'submitted_by',
        'ai_spam_score',
        'status',
        'priority',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'ai_spam_score' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('content_type', $type);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }
}
