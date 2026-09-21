<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->string('category', 50)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ai_handled')->default(false);
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->foreignId('last_reply_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('message_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('category');
            $table->index('assigned_to');
            $table->index('last_reply_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_conversations');
    }
};
