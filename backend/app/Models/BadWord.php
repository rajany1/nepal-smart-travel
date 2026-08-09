<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadWord extends Model
{
    protected $fillable = ['word', 'severity', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];
}
