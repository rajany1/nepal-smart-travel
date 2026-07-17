<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelTranslation extends Model
{
    protected $fillable = [
        'translatable_type', 'translatable_id', 'locale', 'field', 'value', 'source',
    ];

    protected function casts(): array
    {
        return [
            'translatable_id' => 'integer',
        ];
    }
}
