<?php

use App\Models\GameSetting;

if (! function_exists('admin_prefix')) {
    function admin_prefix(): string
    {
        return GameSetting::getValue('admin_route_prefix', 'admin') ?? 'admin';
    }
}
