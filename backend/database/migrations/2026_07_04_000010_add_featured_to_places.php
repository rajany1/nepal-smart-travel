<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('featured_type')->nullable()->after('is_featured'); // featured, top_recommendation, sponsored
            $table->timestamp('featured_expires_at')->nullable()->after('featured_type');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['featured_type', 'featured_expires_at']);
        });
    }
};
