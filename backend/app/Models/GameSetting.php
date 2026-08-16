<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class GameSetting extends Model
{
    protected $table = 'game_settings';
    protected $fillable = ['key', 'value'];
    public $timestamps = true;

    /** Per-process cache to avoid a DB round-trip per lookup (hot paths: ad serving, spend calc). */
    protected static array $cache = [];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        if (! Schema::hasTable('game_settings')) {
            return $default;
        }

        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        if (is_numeric($setting->value)) {
            $value = strpos($setting->value, '.') !== false ? (float) $setting->value : (int) $setting->value;
        } else {
            $decoded = json_decode($setting->value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
        }

        return static::$cache[$key] = $value;
    }

    public static function setValue(string $key, mixed $value): static
    {
        if (! Schema::hasTable('game_settings')) {
            throw new \RuntimeException('game_settings table does not exist. Run migrations.');
        }
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );
        static::$cache[$key] = $setting->value;

        return $setting;
    }
}
