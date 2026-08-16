<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('game_settings')->updateOrInsert(['key' => 'ad_freq_cap'], ['value' => '3', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('game_settings')->where('key', 'ad_freq_cap')->delete();
    }
};
