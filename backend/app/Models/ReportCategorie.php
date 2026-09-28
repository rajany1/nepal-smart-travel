<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCategorie extends Model
{
    protected $table = 'report_categories';

    protected $fillable = [
        'name',
        'name_ne',
        'slug',
        'icon',
        'description',
        'description_ne',
        'category_group_id',
        'sort_order',
        'is_active',
        'is_featured',
        'is_emergency',
        'usage_count',
        'icon_type',
        'icon_color',
        'icon_background',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_emergency' => 'boolean',
        'usage_count' => 'integer',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'category_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ReportCategoryGroup::class, 'category_group_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ReportCategoryOption::class, 'category_id')->where('is_active', true)->orderBy('sort_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ReportCategoryField::class, 'category_id')->where('is_active', true)->orderBy('sort_order');
    }

    public function activeReports(): HasMany
    {
        return $this->reports()->where('is_active', true);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}