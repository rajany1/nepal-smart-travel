<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCategoryOption extends Model
{
    protected $table = 'report_category_options';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'name_ne',
        'description',
        'description_ne',
        'icon',
        'icon_type',
        'sort_order',
        'is_active',
        'requires_photo',
        'requires_location',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'requires_photo' => 'boolean',
        'requires_location' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategorie::class, 'category_id');
    }
}