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
        Schema::create('report_category_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->constrained('report_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('name_ne')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ne')->nullable();
            $table->string('icon')->nullable();
            $table->string('icon_type')->default('material');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_photo')->default(false);
            $table->boolean('requires_location')->default(false);

            $table->timestamps();

            $table->index(['category_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_category_options');
    }
};