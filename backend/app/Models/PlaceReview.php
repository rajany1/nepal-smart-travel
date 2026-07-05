<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceReview extends Model
{
    protected $guarded = ['id', 'uuid'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'helpful_count' => 'integer',
        ];
    }
}
