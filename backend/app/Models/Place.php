<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Place extends Model
{
    protected $fillable = [
        'uuid',
        'category_id',
        'created_by',
        'name',
        'description',
        'address',
        'district',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'average_rating',
        'total_reviews',
        'is_verified',
        'is_featured',
        'featured_type',
        'featured_expires_at',
        'is_active',
        'featured_until',
        'source',
        'osm_id',
        'osm_type',
        'imported_at',
        'wikidata_id',
        'wikipedia_title',
        'commons_category',
        'image_discovery_status',
        'image_discovered_at',
        'image_attempt_count',
        'image_next_retry_at',
        'is_open',
        'opening_hours',
        'today_offer',
        'live_event',
        'last_status_update',
    ];

    /** Image-discovery states stored in places.image_discovery_status. */
    public const IMAGE_STATUS_PENDING = 'pending';
    public const IMAGE_STATUS_RUNNING = 'running';
    public const IMAGE_STATUS_DONE = 'done';
    public const IMAGE_STATUS_PARTIAL = 'partial';
    public const IMAGE_STATUS_NONE = 'none';
    public const IMAGE_STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'average_rating' => 'decimal:1',
            'total_reviews' => 'integer',
            'is_verified' => 'boolean',
            'is_featured' => 'boolean',
            'featured_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'featured_until' => 'datetime',
            'is_open' => 'boolean',
            'opening_hours' => 'array',
            'last_status_update' => 'datetime',
            'image_discovered_at' => 'datetime',
            'image_attempt_count' => 'integer',
            'image_next_retry_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Place $place) {
            if (empty($place->uuid)) {
                $place->uuid = (string) Str::uuid();
            }
            // Reject places outside Nepal boundary
            if ($place->latitude && $place->longitude) {
                if ($place->latitude < 26.35 || $place->latitude > 30.45 ||
                    $place->longitude < 80.05 || $place->longitude > 88.60) {
                    throw new \Exception('Place coordinates are outside Nepal边界');
                }
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)
            ->where(function ($q) {
                $q->whereNull('featured_until')
                  ->orWhere('featured_until', '>=', now());
            });
    }

    public function category()
    {
        return $this->belongsTo(PlaceCategories::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviews()
    {
        return $this->hasMany(PlaceReview::class, 'place_id');
    }

    public function approvedReviews()
    {
        return $this->hasMany(PlaceReview::class, 'place_id')
            ->where(function ($q) {
                $q->whereNull('moderation_status')
                    ->orWhere('moderation_status', 'approved');
            });
    }

    public function images()
    {
        return $this->hasMany(PlaceImage::class, 'place_id')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    /** Public gallery: approved images only, primary first. */
    public function galleryImages()
    {
        return $this->hasMany(PlaceImage::class, 'place_id')
            ->where('status', PlaceImage::STATUS_APPROVED)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    /**
     * Exactly one row — for list/map endpoints that only need a cover image.
     * Eager-loading the full gallery there used to be free when every place
     * had ≤1 image; at 3–10 images per place it would multiply the payload.
     */
    public function primaryImage()
    {
        return $this->hasOne(PlaceImage::class, 'place_id')
            ->where('status', PlaceImage::STATUS_APPROVED)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    /** Additive API field: full gallery with attribution (URLs unchanged). */
    public function imageGallery(): array
    {
        return $this->galleryImages
            ->map(fn (PlaceImage $image) => $image->credit())
            ->filter()
            ->values()
            ->all();
    }
}