<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCategoryField extends Model
{
    protected $table = 'report_category_fields';

    protected $fillable = [
        'category_id',
        'name',
        'label',
        'label_ne',
        'placeholder',
        'placeholder_ne',
        'type',
        'required',
        'is_active',
        'sort_order',
        'options',
        'validation',
        'help_text',
        'help_text_ne',
        'show_in_preview',
    ];

    protected $casts = [
        'required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'options' => 'array',
        'validation' => 'array',
        'show_in_preview' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategorie::class, 'category_id');
    }

    public function getOptionsArray(): array
    {
        return $this->options ?? [];
    }
}