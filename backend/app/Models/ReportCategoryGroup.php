<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCategoryGroup extends Model
{
    protected $table = 'report_category_groups';

    protected $fillable = [
        'name',
        'slug',
        'name_ne',
        'description',
        'description_ne',
        'icon',
        'icon_type',
        'icon_color',
        'icon_background',
        'sort_order',
        'is_active',
        'is_emergency_group',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_emergency_group' => 'boolean',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(ReportCategorie::class, 'category_group_id');
    }

    public function activeCategories(): HasMany
    {
        return $this->categories()->where('is_active', true)->orderBy('sort_order');
    }

    public function featuredCategories(): HasMany
    {
        return $this->activeCategories()->where('is_featured', true);
    }
}