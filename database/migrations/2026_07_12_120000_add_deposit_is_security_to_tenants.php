<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'deposit_is_security')) {
                // When true, the deposit/booking fee is treated as a separate
                // refundable security deposit: the balance reminder asks the
                // guest to pay the FULL stay total, and the host refunds the
                // deposit after check-out. Default false preserves the legacy
                // behaviour (deposit credited toward the total; reminder chases
                // total − deposit).
                $table->boolean('deposit_is_security')->default(false)->after('auto_cancel_unpaid_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'deposit_is_security')) {
                $table->dropColumn('deposit_is_security');
            }
        });
    }
};
