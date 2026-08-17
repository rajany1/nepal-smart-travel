<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationGlossary extends Model
{
    protected $table = 'translation_glossary';

    protected $fillable = [
        'term', 'nepali', 'context', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
