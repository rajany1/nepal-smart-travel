<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceCorrection extends Model
{
    protected $fillable = [
        'place_id',
        'osm_id',
        'place_name',
        'user_id',
        'correction_type',
        'description',
        'suggested_name',
        'suggested_latitude',
        'suggested_longitude',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'suggested_latitude' => 'float',
        'suggested_longitude' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
