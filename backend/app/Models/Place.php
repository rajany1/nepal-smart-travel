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
    ];

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
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Place $place) {
            if (empty($place->uuid)) {
                $place->uuid = (string) Str::uuid();
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

    public function images()
    {
        return $this->hasMany(PlaceImage::class, 'place_id');
    }
}