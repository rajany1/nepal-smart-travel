<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single place photo plus its full provenance.
 *
 * Two kinds of row share this table:
 *  - source='upload'    : locally stored files (admin/user uploads). image_url
 *                         is a relative path on the public disk.
 *  - source='wikimedia' : externally hosted Wikimedia Commons files. image_url
 *                         is a full https URL (a 1200px thumbnail — never the
 *                         original, which can be tens of MB).
 *
 * displayUrl()/resolveUrl() is the ONE place that decides which of those two
 * shapes a caller gets, so no read path ever builds "asset('storage/https://…')".
 */
class PlaceImage extends Model
{
    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_WIKIMEDIA = 'wikimedia';

    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'place_id',
        'image_url',
        'thumbnail_url',
        'original_url',
        'source',
        'source_id',
        'source_page_url',
        'title',
        'author',
        'license',
        'license_url',
        'attribution',
        'width',
        'height',
        'sha1',
        'photo_latitude',
        'photo_longitude',
        'match_score',
        'matched_via',
        'is_primary',
        'sort_order',
        'status',
        'discovered_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'photo_latitude' => 'decimal:7',
            'photo_longitude' => 'decimal:7',
            'match_score' => 'decimal:3',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'discovered_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Turn any stored image_url into something a browser/Flutter can load.
     * External URLs pass through untouched; local paths become public-disk
     * asset URLs.
     */
    public static function resolveUrl(?string $imageUrl): ?string
    {
        if ($imageUrl === null || $imageUrl === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $imageUrl) === 1) {
            return $imageUrl;
        }

        return asset('storage/' . ltrim($imageUrl, '/'));
    }

    public function displayUrl(): ?string
    {
        return self::resolveUrl($this->image_url);
    }

    /** Display thumbnail when we have one, otherwise the main URL. */
    public function displayThumbUrl(): ?string
    {
        return self::resolveUrl($this->thumbnail_url ?: $this->image_url);
    }

    /** Machine-readable attribution payload for the API (additive field). */
    public function credit(): array
    {
        return array_filter([
            'url' => $this->displayUrl(),
            'thumbnail_url' => $this->displayThumbUrl(),
            'width' => $this->width,
            'height' => $this->height,
            'author' => $this->author,
            'license' => $this->license,
            'license_url' => $this->license_url,
            'attribution' => $this->attribution,
            'source' => $this->source,
            'source_page_url' => $this->source_page_url,
            'is_primary' => $this->is_primary,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Gallery ordering: primary first, then explicit sort order, then newest
     * first so the pre-existing upload-only rows keep a stable, sensible order.
     */
    public function scopeGallery(Builder $query): Builder
    {
        return $query->approved()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }
}
