<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_category_groups', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('name_ne')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ne')->nullable();
            $table->string('icon')->nullable();
            $table->string('icon_type')->default('material'); // material, custom, emoji
            $table->string('icon_color')->nullable();
            $table->string('icon_background')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_emergency_group')->default(false); // for safety-critical groups

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_category_groups');
    }
};