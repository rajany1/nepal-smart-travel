<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->boolean('is_open')->nullable()->after('is_active');
            $table->json('opening_hours')->nullable()->after('is_open');
            $table->string('today_offer')->nullable()->after('opening_hours');
            $table->string('live_event')->nullable()->after('today_offer');
            $table->timestamp('last_status_update')->nullable()->after('live_event');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn([
                'is_open', 'opening_hours', 'today_offer',
                'live_event', 'last_status_update',
            ]);
        });
    }
};
