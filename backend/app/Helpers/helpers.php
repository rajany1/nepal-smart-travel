<?php

use App\Models\GameSetting;

if (! function_exists('admin_prefix')) {
    function admin_prefix(): string
    {
        return GameSetting::getValue('admin_route_prefix', 'admin') ?? 'admin';
    }
}

if (! function_exists('avatar_url')) {
    function avatar_url(?string $avatar): ?string
    {
        if (empty($avatar)) return null;
        if (str_starts_with($avatar, 'http')) return $avatar;
        return asset('storage/' . $avatar);
    }
}
