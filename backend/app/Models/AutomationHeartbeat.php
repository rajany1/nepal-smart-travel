<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationHeartbeat extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['name', 'meta', 'updated_at'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'updated_at' => 'datetime',
        ];
    }
}
