<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('badges')->default('[]');
            $table->json('expertise_regions')->default('[]');
            $table->integer('total_reports')->default(0);
            $table->decimal('approval_rate', 5, 2)->default(0.00);
            $table->integer('rank')->default(0);
            $table->timestamp('last_contribution_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'badges',
                'expertise_regions',
                'total_reports',
                'approval_rate',
                'rank',
                'last_contribution_at',
            ]);
        });
    }
};