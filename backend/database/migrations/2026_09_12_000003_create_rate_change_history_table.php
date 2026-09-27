<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rate_change_history')) {
            return;
        }

        Schema::create('rate_change_history', function (Blueprint $table) {
            $table->id();
            $table->string('setting_table', 50); // coin_settings or game_settings
            $table->string('setting_key', 50);
            $table->decimal('old_value', 12, 4);
            $table->decimal('new_value', 12, 4);
            $table->foreignId('admin_id')->constrained('users');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['setting_table', 'setting_key', 'created_at']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_change_history');
    }
};
