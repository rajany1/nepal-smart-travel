<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed Nepali profanity — Devanagari script, romanized (Nepali in English
 * letters), and the common vulgar variants/insults — so the content safety
 * agent censors Nepali too, not just English.
 */
return new class extends Migration
{
    private const WORDS = [
        // severe sexual/parental slurs — Devanagari + romanized
        ['madarchod', 'severe'], ['मादरचोद', 'severe'], ['मादरचौद', 'severe'],
        ['behenchod', 'severe'], ['बेहेनचोद', 'severe'], ['बहनचोद', 'severe'],
        ['chutiya', 'severe'], ['चुटिया', 'severe'], ['चुतिया', 'severe'],
        ['chod', 'severe'], ['चोद', 'severe'], ['चुद', 'severe'], ['चूद', 'severe'], ['चोत', 'severe'],
        ['gaand', 'severe'], ['गांड', 'severe'], ['गाँड', 'severe'], ['गाण्ड', 'severe'],
        ['choot', 'severe'], ['चूत', 'severe'], ['चुत', 'severe'],
        ['bhosada', 'severe'], ['भोसडा', 'severe'], ['भोस्डा', 'severe'], ['भोसड़ा', 'severe'],
        ['gandu', 'severe'], ['गांडु', 'severe'], ['गान्डु', 'severe'], ['गन्डु', 'severe'],
        ['randi', 'severe'], ['रांडी', 'severe'], ['रान्डी', 'severe'], ['रांड', 'severe'],
        ['lund', 'severe'], ['लंड', 'severe'], ['लौंडा', 'severe'],
        ['kutta', 'severe'], ['कुत्ता', 'severe'],
        // moderate common insults
        ['lauda', 'moderate'], ['लौडा', 'moderate'], ['लौडे', 'moderate'],
        ['kukur', 'moderate'], ['कुकुर', 'moderate'],
        ['suar', 'moderate'], ['सुअर', 'moderate'],
        ['kamina', 'moderate'], ['कमिना', 'moderate'],
        ['harami', 'moderate'], ['हरामी', 'moderate'],
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