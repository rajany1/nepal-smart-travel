<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_item_id')->constrained()->cascadeOnDelete();
            $table->text('code');
            $table->boolean('is_used')->default(false);
            $table->foreignId('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['shop_item_id', 'is_used']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_codes');
    }
};
