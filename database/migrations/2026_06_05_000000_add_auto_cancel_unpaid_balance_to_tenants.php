<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'auto_cancel_unpaid_balance')) {
                // Default OFF: most homestay hosts collect the balance on
                // arrival, so a confirmed (deposit-paid) booking must NOT be
                // auto-cancelled just because the balance is still outstanding.
                // Hosts who require full prepayment can opt in.
                $table->boolean('auto_cancel_unpaid_balance')
                    ->default(false)
                    ->after('cancel_balance_on');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'auto_cancel_unpaid_balance')) {
                $table->dropColumn('auto_cancel_unpaid_balance');
            }
        });
    }
};
