<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Censored-word dictionary (24/7 review AI agent's first line of defence)
        Schema::create('bad_words', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100)->unique();
            $table->enum('severity', ['mild', 'moderate', 'severe'])->default('moderate');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Every censor decision - the accountability record (who/what/when/why)
        Schema::create('content_violations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('entity_type', 40)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('field', 40);
            $table->text('original_text');
            $table->text('censored_text');
            $table->json('found_words')->nullable();
            $table->enum('severity', ['mild', 'moderate', 'severe'])->default('moderate');
            $table->string('action', 20)->default('censored'); // censored | flagged
            $table->string('source', 20)->default('system');   // system | ai | realtime
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();
        });

        // Escalation ladder: warning -> suspend -> block (each offense recorded)
        Schema::create('moderation_strikes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('level', ['warning', 'suspend', 'block']);
            $table->text('reason');
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable(); // null = system (AI agent)
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_until')->nullable()->after('status');
        });

        // ---- seed wordlist (English + romanized Nepali) ----
        $words = [
            // severe
            'fuck', 'fucker', 'fucking', 'fucked', 'fuckhead', 'fuckface', 'motherfucker', 'motherfucking',
            'cunt', 'whore', 'slut', 'bitch', 'dick', 'dickhead', 'cock', 'pussy', 'cocksucker',
            'nigger', 'nigga', 'faggot', 'fag', 'retard', 'rape',
            'randi', 'chikne', 'chikni', 'maachikne', 'bhenchod', 'behenchod', 'chhakka', 'gando', 'lado',
            // moderate
            'asshole', 'ass', 'arse', 'bastard', 'twat', 'prick', 'wanker', 'dumbass', 'jackass',
            'douchebag', 'bullshit', 'shit', 'crap', 'dammit', 'god damn', 'shithead', 'shits',
            'chutiya', 'kutte', 'kutta', 'bhaindi', 'muh me lena',
            // mild
            'damn', 'hell', 'bloody', 'screw you', 'bollocks',
        ];
        $severity = ['severe', 'moderate', 'mild'];
        $now = now();
        $rows = [];
        foreach ($words as $i => $word) {
            $rows[] = [
                'word' => $word,
                'severity' => $i < 35 ? 'severe' : ($i < 48 ? 'moderate' : 'mild'),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('bad_words')->insert($rows);

        // ---- safety policy settings ----
        $settings = [
            'safety_warn_at_strikes' => '1',      // warn on 1st offense
            'safety_suspend_at_strikes' => '2',   // suspend on 2nd
            'safety_block_at_strikes' => '3',     // block on 3rd
            'safety_suspend_hours' => '24',
            'safety_strike_window_days' => '30',
            'safety_ai_enabled' => '1',
        ];
        foreach ($settings as $key => $value) {
            DB::table('game_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_strikes');
        Schema::dropIfExists('content_violations');
        Schema::dropIfExists('bad_words');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('suspended_until');
        });
        DB::table('game_settings')->whereIn('key', [
            'safety_warn_at_strikes', 'safety_suspend_at_strikes', 'safety_block_at_strikes',
            'safety_suspend_hours', 'safety_strike_window_days', 'safety_ai_enabled',
        ])->delete();
    }
};
