<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_partner_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, paid, rejected
            $table->string('payment_method')->nullable(); // esewa, khalti, bank
            $table->string('payment_detail')->nullable(); // esewa id / account no
            $table->text('note')->nullable(); // partner message
            $table->text('admin_note')->nullable(); // admin remark / reject reason
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $permId = DB::table('permissions')->insertGetId([
            'name' => 'manage_payouts',
            'display_name' => 'Manage Payouts',
            'description' => 'View and process partner payout requests',
            'group' => 'monetization',
            'menu_label' => 'Payouts',
            'menu_icon' => 'hand-holding-usd',
            'menu_order' => 160,
            'menu_group' => 'monetization',
            'route_name' => 'admin.payouts',
            'is_system' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_has_permissions')->insertOrIgnore([
            'role_id' => DB::table('roles')->where('name', 'admin')->value('id'),
            'permission_id' => $permId,
        ]);
    }

    public function down(): void
    {
        $perm = DB::table('permissions')->where('name', 'manage_payouts')->first();
        if ($perm) {
            DB::table('role_has_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('permissions')->where('id', $perm->id)->delete();
        }
        Schema::dropIfExists('payouts');
    }
};
