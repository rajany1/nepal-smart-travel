<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add structured fields to reports
        Schema::table('reports', function (Blueprint $table) {
            $table->string('location_name')->nullable()->after('district');
            $table->string('report_subcategory')->nullable()->after('category_id');
            $table->boolean('is_active')->default(true)->after('status');
            $table->timestamp('expires_at')->nullable()->after('is_active');
            $table->integer('confirmed_by_count')->default(0)->after('comments_count');
            $table->timestamp('last_confirmed_at')->nullable()->after('confirmed_by_count');
        });

        // Community confirmation table
        Schema::create('report_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_confirmations');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn([
                'location_name', 'report_subcategory', 'is_active',
                'expires_at', 'confirmed_by_count', 'last_confirmed_at',
            ]);
        });
    }
};
