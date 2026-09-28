<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('reverses_transaction_id')->nullable()->after('report_id');

            $table->foreign('reverses_transaction_id', 'coin_transactions_reverses_transaction_id_foreign')
                ->references('id')->on('coin_transactions')
                ->nullOnDelete();

            // One reversal per original credit. NULLs do not collide in MySQL,
            // so ordinary transactions are unaffected.
            $table->unique('reverses_transaction_id', 'coin_transactions_reverses_transaction_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->dropUnique('coin_transactions_reverses_transaction_id_unique');
            $table->dropForeign('coin_transactions_reverses_transaction_id_foreign');
            $table->dropColumn('reverses_transaction_id');
        });
    }
};
