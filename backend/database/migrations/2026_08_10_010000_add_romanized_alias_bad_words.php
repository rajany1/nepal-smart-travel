<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Common romanized spelling variants that bypass the base wordlist
 * (chutia vs chutiya, kukkur vs kukur, chudai, ...).
 */
return new class extends Migration
{
    private const WORDS = [
        ['chutia', 'severe'], ['chudai', 'severe'], ['chodai', 'severe'],
        ['madarchud', 'severe'], ['gand', 'severe'],
        ['kukkur', 'moderate'],
    ];

    public function up(): void
    {
        foreach (self::WORDS as [$word, $severity]) {
            DB::table('bad_words')->updateOrInsert(
                ['word' => $word],
                ['severity' => $severity, 'active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('bad_words')->whereIn('word', array_column(self::WORDS, 0))->delete();
    }
};