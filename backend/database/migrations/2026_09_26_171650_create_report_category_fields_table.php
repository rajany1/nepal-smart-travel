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
        Schema::create('report_category_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->constrained('report_categories')->cascadeOnDelete();
            $table->string('name'); // field name/key
            $table->string('label');
            $table->string('label_ne')->nullable();
            $table->text('placeholder')->nullable();
            $table->text('placeholder_ne')->nullable();
            $table->string('type'); // single_select, multi_select, text, textarea, number, photo, location, severity, date, time, yes_no
            $table->boolean('required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('options')->nullable(); // for select types: [{value, label, label_ne}]
            $table->json('validation')->nullable(); // Laravel validation rules
            $table->text('help_text')->nullable();
            $table->text('help_text_ne')->nullable();
            $table->boolean('show_in_preview')->default(true);

            $table->timestamps();

            $table->index(['category_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_category_fields');
    }
};