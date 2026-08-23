<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add new columns for verification tick metadata
            $table->text('verification_tick_reason')->nullable()->after('verification_tick');
            $table->foreignId('verification_tick_assigned_by')->nullable()->constrained('users')->nullOnDelete()->after('verification_tick_reason');
            $table->timestamp('verification_tick_assigned_at')->nullable()->after('verification_tick_assigned_by');
            
            // Index for querying by tick type
            $table->index('verification_tick');
        });

        // Update existing enum: none, gray, blue, yellow (remove green, gold)
        // We need to handle existing 'green' and 'gold' values
        DB::statement("ALTER TABLE users MODIFY COLUMN verification_tick ENUM('none', 'gray', 'blue', 'yellow') DEFAULT 'none'");

        // Migrate old values: green -> yellow (admin assigned), gold -> blue (performance)
        DB::table('users')->where('verification_tick', 'green')->update(['verification_tick' => 'yellow']);
        DB::table('users')->where('verification_tick', 'gold')->update(['verification_tick' => 'blue']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['verification_tick']);
            $table->dropForeign(['verification_tick_assigned_by']);
            $table->dropColumn([
                'verification_tick_reason',
                'verification_tick_assigned_by',
                'verification_tick_assigned_at',
            ]);
        });

        // Revert enum to original
        DB::statement("ALTER TABLE users MODIFY COLUMN verification_tick ENUM('none', 'blue', 'green', 'gold') DEFAULT 'none'");
        
        // Revert migrated values
        DB::table('users')->where('verification_tick', 'yellow')->update(['verification_tick' => 'green']);
        DB::table('users')->where('verification_tick', 'gray')->update(['verification_tick' => 'none']);
    }
};