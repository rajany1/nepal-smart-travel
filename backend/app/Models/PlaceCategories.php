<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceCategories extends Model
{
    protected $table = 'place_categories';

    protected $fillable = [
        'name',
        'icon',
    ];

    public function places()
    {
        return $this->hasMany(Place::class, 'category_id');
    }
}