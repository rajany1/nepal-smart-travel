<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name', 'display_name', 'description', 'group', 'is_system',
        'menu_label', 'menu_icon', 'menu_order', 'route_name', 'menu_group',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'menu_order' => 'integer',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }
}
