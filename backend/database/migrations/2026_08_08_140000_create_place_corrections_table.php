<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id')->nullable()->index();
            $table->string('osm_id', 50)->nullable()->index();
            $table->string('place_name')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('correction_type', [
                'wrong_location',
                'wrong_name',
                'closed',
                'duplicate',
                'outdated_info',
                'other',
            ]);
            $table->text('description')->nullable();
            $table->string('suggested_name', 255)->nullable();
            $table->decimal('suggested_latitude', 10, 7)->nullable();
            $table->decimal('suggested_longitude', 10, 7)->nullable();
            $table->enum('status', ['pending', 'applied', 'rejected'])->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_corrections');
    }
};
