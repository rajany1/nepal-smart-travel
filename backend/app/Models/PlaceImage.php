<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceImage extends Model
{
    protected $fillable = [
        'place_id',
        'image_url',
    ];
}
