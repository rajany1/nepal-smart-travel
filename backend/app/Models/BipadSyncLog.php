<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BipadSyncLog extends Model
{
    protected $fillable = [
        'endpoint',
        'status',
        'records_fetched',
        'records_created',
        'records_updated',
        'records_skipped',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'records_fetched' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_skipped' => 'integer',
    ];

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeForEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', $endpoint);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function getDurationMsAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        
        return $this->started_at->diffInMilliseconds($this->completed_at);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->records_fetched === 0) {
            return 0.0;
        }
        
        return (($this->records_created + $this->records_updated) / $this->records_fetched) * 100;
    }
}