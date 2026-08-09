<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_purchases');
        Schema::dropIfExists('shop_codes');
        Schema::dropIfExists('shop_items');
        Schema::dropIfExists('sponsors');
    }

    public function down(): void
    {
        // Legacy store tables removed by design (2026-08-08 rework).
    }
};