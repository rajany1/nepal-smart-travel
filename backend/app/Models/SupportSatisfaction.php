<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportSatisfaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_conversation_id',
        'user_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    const RATINGS = [
        1 => 'Very dissatisfied',
        2 => 'Dissatisfied',
        3 => 'Neutral',
        4 => 'Satisfied',
        5 => 'Very satisfied',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ratingLabel(): string
    {
        return self::RATINGS[$this->rating] ?? 'Unknown';
    }
}
