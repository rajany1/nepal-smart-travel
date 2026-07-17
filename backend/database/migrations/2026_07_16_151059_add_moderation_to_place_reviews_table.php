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
        Schema::table('place_reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('place_reviews', 'moderated_at')) {
                $table->timestamp('moderated_at')->nullable()->after('rating');
            }
            if (!Schema::hasColumn('place_reviews', 'moderation_status')) {
                $table->string('moderation_status', 20)->nullable()->after('moderated_at');
            }
            if (!Schema::hasColumn('place_reviews', 'moderated_by')) {
                $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete()->after('moderation_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('place_reviews', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('place_reviews', 'moderated_by')) {
                $table->dropForeign(['moderated_by']);
                $drop[] = 'moderated_by';
            }
            if (Schema::hasColumn('place_reviews', 'moderation_status')) {
                $drop[] = 'moderation_status';
            }
            if (Schema::hasColumn('place_reviews', 'moderated_at')) {
                $drop[] = 'moderated_at';
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
