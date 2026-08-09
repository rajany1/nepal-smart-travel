<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow places in the moderation queue (admin approve/reject flow)
     * and make submitted_by nullable (admin/OSM places have no creator).
     */
    public function up(): void
    {
        Schema::table('moderation_queues', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->foreignId('submitted_by')->nullable()->change();
            $table->foreign('submitted_by')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('content_type', [
                'report',
                'review',
                'comment',
                'place',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('moderation_queues', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->foreignId('submitted_by')->nullable(false)->change();
            $table->foreign('submitted_by')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('content_type', [
                'report',
                'review',
                'comment',
            ])->change();
        });
    }
};
