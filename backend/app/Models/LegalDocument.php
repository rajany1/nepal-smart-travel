<?php

namespace App\Models;

use App\Services\HtmlSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class LegalDocument extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Content shown until an admin enters the real legal text.
     */
    public const PLACEHOLDER = '[LEGAL CONTENT TO BE ADDED BY ADMIN]';

    protected $fillable = [
        'type',
        'slug',
        'title',
        'short_description',
        'reference_url',
        'content',
        'version',
        'is_published',
        'status',
        'effective_date',
        'published_at',
        'last_edited_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'effective_date' => 'date',
    ];

    /**
     * Keep is_published in sync whenever status is assigned.
     */
    public function setStatusAttribute($value): void
    {
        $value = in_array($value, [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED], true)
            ? $value
            : self::STATUS_DRAFT;

        $this->attributes['status'] = $value;
        $this->attributes['is_published'] = $value === self::STATUS_PUBLISHED;
    }

    /**
     * Keep status in sync when is_published is assigned directly
     * (legacy callers, tests, API code). Never un-archives an archived row.
     */
    public function setIsPublishedAttribute($value): void
    {
        $published = (bool) $value;

        if ($published) {
            $this->attributes['status'] = self::STATUS_PUBLISHED;
            $this->attributes['is_published'] = true;

            return;
        }

        $this->attributes['is_published'] = false;
        if (($this->attributes['status'] ?? null) !== self::STATUS_ARCHIVED) {
            $this->attributes['status'] = self::STATUS_DRAFT;
        }
    }

    /**
     * Get the active (latest published) document for a given type.
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
     * All available document types (from database).
     */
    public static function types(): array
    {
        if (Schema::hasTable('legal_document_types')) {
            return LegalDocumentType::getActiveTypes();
        }

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

    /**
     * Scope: resolve by public slug or legacy type identifier.
     */
    public function scopeWhereSlugOrType($query, string $identifier)
    {
        return $query->where(function ($q) use ($identifier) {
            $q->where('slug', $identifier)->orWhere('type', $identifier);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublishedStatus(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * The optional external/reference URL, but only when its scheme is safe
     * (https/http/mailto/site-relative). Views and API must use this accessor
     * instead of reference_url directly.
     */
    public function getSafeReferenceUrlAttribute(): ?string
    {
        if (! HtmlSanitizer::isSafeUrl($this->reference_url)) {
            return null;
        }

        return $this->reference_url;
    }
}
