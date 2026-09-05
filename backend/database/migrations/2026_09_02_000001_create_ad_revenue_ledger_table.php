<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_revenue_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->string('context', 50)->comment('Screen where ad was shown: report, home, explore, etc.');
            $table->decimal('gross_amount', 12, 4)->default(0)->comment('What partner paid for this impression/click');
            $table->decimal('user_share', 12, 4)->default(0)->comment('Coins credited to user (report screen only)');
            $table->decimal('admin_share', 12, 4)->default(0)->comment('Platform retains (always)');
            $table->string('event_type', 20)->comment('impression or click');
            $table->timestamps();

            $table->index(['context', 'created_at']);
            $table->index(['ad_campaign_id', 'created_at']);
            $table->index(['report_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_revenue_ledger');
    }
};
