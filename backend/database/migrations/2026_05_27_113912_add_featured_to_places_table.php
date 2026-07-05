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
        Schema::table('places', function (Blueprint $table) {
            if (!Schema::hasColumn('places', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_verified');
            }
            if (!Schema::hasColumn('places', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_featured');
            }
            if (!Schema::hasColumn('places', 'featured_until')) {
                $table->timestamp('featured_until')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $columns = ['is_featured', 'is_active', 'featured_until'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('places', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
