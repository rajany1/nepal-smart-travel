<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',        // 'privacy_policy', 'terms_conditions', 'about', 'community_guidelines', etc.
        'title',
        'content',     // HTML content
        'version',
        'is_published',
        'published_at',
        'last_edited_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Get the active document for a given type.
     */
    public static function getActive(string $type): ?self
    {
        return static::where('type', $type)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->first();
    }

    /**
     * Get the latest draft/document for admin editing.
     */
    public static function getLatest(string $type): ?self
    {
        return static::where('type', $type)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * All available document types.
     */
    public static function types(): array
    {
        return [
            'privacy_policy' => 'Privacy Policy',
            'terms_conditions' => 'Terms & Conditions',
            'about' => 'About',
            'community_guidelines' => 'Community Guidelines',
            'content_policy' => 'Content Policy',
            'emergency_policy' => 'Emergency Policy',
        ];
    }

    /**
     * Scope: only published documents.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
