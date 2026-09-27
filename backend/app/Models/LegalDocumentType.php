<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalDocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'label',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all active types as [slug => label] array.
     */
    public static function getActiveTypes(): array
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'slug')
            ->toArray();
    }

    /**
     * Get all types (active + inactive) as [slug => label] array.
     */
    public static function getAllTypes(): array
    {
        return static::orderBy('sort_order')
            ->pluck('label', 'slug')
            ->toArray();
    }
}
