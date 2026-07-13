<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep only the latest active/trialing subscription per user
        $duplicates = DB::table('user_subscriptions')
            ->select('user_id', DB::raw('MAX(created_at) as latest'))
            ->whereIn('status', ['active', 'trialing'])
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('user_subscriptions')
                ->where('user_id', $dup->user_id)
                ->whereIn('status', ['active', 'trialing'])
                ->where('created_at', '<', $dup->latest)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'ends_at' => now(),
                ]);
        }

        // Rebuild index without dropping (FK constraint needs it)
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->index('status', 'user_subscriptions_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex('user_subscriptions_user_id_index');
            $table->dropIndex('user_subscriptions_status_index');
            $table->index(['user_id', 'status']);
        });
    }
};
