<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // 1:1 targeted alert for a specific user (e.g. content-safety notice).
            if (!Schema::hasColumn('alerts', 'target_user_id')) {
                $table->unsignedBigInteger('target_user_id')->nullable()->after('source_id');
            }
            // Who created/spoke in this alert: user | admin | system.
            if (!Schema::hasColumn('alerts', 'sender_type')) {
                $table->string('sender_type', 20)->default('user')->after('target_user_id');
            }
            // Deep-link metadata for tap navigation.
            // link_type: report | external | screen ; link_value holds the id/url/route.
            if (!Schema::hasColumn('alerts', 'link_type')) {
                $table->string('link_type', 40)->nullable()->after('sender_type');
            }
            if (!Schema::hasColumn('alerts', 'link_value')) {
                $table->string('link_value', 500)->nullable()->after('link_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            foreach (['target_user_id', 'sender_type', 'link_type', 'link_value'] as $col) {
                if (Schema::hasColumn('alerts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};