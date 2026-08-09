<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentViolation extends Model
{
    protected $fillable = [
        'user_id', 'entity_type', 'entity_id', 'field',
        'original_text', 'censored_text', 'found_words',
        'severity', 'action', 'source', 'reviewed_by',
    ];

    protected $casts = [
        'found_words' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
