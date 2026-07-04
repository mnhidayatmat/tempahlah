<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual (offline) payment option for the public booking page.
 *
 * When enabled, guests on the tenant subdomain can choose to pay via bank
 * transfer / cash instead of the online gateway. They still receive an
 * invoice; the host manually marks the booking fee / full payment as paid
 * in the dashboard, which fires the confirmation + receipt comms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'manual_payment_enabled')) {
                // Default ON — manual bank transfer is the common Malaysian
                // homestay flow and works even before a gateway is connected.
                $table->boolean('manual_payment_enabled')->default(true)->after('auto_cancel_unpaid_balance');
            }
            if (! Schema::hasColumn('tenants', 'manual_payment_instructions')) {
                $table->text('manual_payment_instructions')->nullable()->after('manual_payment_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['manual_payment_enabled', 'manual_payment_instructions'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
