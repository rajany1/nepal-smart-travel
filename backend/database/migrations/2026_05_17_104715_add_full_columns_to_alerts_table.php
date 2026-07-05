<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('alerts', 'uuid')) {
                $table->uuid()->unique()->after('id');
            }
            if (!Schema::hasColumn('alerts', 'title')) {
                $table->string('title')->after('uuid');
            }
            if (!Schema::hasColumn('alerts', 'description')) {
                $table->text('description')->after('title');
            }
            if (!Schema::hasColumn('alerts', 'alert_type')) {
                $table->string('alert_type')->after('description');
            }
            if (!Schema::hasColumn('alerts', 'severity')) {
                $table->string('severity')->default('info')->after('alert_type');
            }
            if (!Schema::hasColumn('alerts', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('severity');
            }
            if (!Schema::hasColumn('alerts', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('alerts', 'affected_district')) {
                $table->string('affected_district')->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('alerts', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('longitude');
            }
            if (!Schema::hasColumn('alerts', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $columns = ['uuid', 'title', 'description', 'alert_type', 'severity', 'latitude', 'longitude', 'affected_district', 'created_by', 'expires_at'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('alerts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};