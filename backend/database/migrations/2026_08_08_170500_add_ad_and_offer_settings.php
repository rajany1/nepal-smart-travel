<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('game_settings')->updateOrInsert(['key' => 'xp_per_npr_ratio'], ['value' => '1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('game_settings')->updateOrInsert(['key' => 'ad_cpm'], ['value' => '50', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('game_settings')->updateOrInsert(['key' => 'ad_cpc'], ['value' => '10', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('game_settings')->whereIn('key', ['xp_per_npr_ratio', 'ad_cpm', 'ad_cpc'])->delete();
    }
};