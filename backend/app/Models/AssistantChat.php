<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantChat extends Model
{
    protected $fillable = [
        'user_id',
        'user_message',
        'ai_response',
    ];
}
