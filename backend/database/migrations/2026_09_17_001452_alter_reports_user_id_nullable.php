<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reports DROP FOREIGN KEY reports_user_id_foreign');
        DB::statement('ALTER TABLE reports MODIFY user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reports MODIFY user_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE reports ADD CONSTRAINT reports_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    }
};
