<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class GameSetting extends Model
{
    protected $table = 'game_settings';
    protected $fillable = ['key', 'value'];
    public $timestamps = true;

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('game_settings')) {
            return $default;
        }

        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        if (is_numeric($setting->value)) {
            return strpos($setting->value, '.') !== false ? (float) $setting->value : (int) $setting->value;
        }

        $decoded = json_decode($setting->value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $setting->value;
    }

    public static function setValue(string $key, mixed $value): static
    {
        if (! Schema::hasTable('game_settings')) {
            throw new \RuntimeException('game_settings table does not exist. Run migrations.');
        }
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );
    }
}
