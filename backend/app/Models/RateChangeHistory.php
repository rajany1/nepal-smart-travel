<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateChangeHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'setting_table',
        'setting_key',
        'old_value',
        'new_value',
        'admin_id',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'old_value' => 'decimal:4',
        'new_value' => 'decimal:4',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
