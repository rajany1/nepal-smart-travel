<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_partners', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('is_active');
            $table->text('rejected_reason')->nullable()->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('travel_partners', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'verification_status', 'rejected_reason']);
        });
    }
};
