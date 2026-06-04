<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-configurable payment lifecycle + refund policy.
 *
 * tenants:
 *   - full_payment_days_before : balance reminder/due lead time (X days before check-in)
 *   - fee_payment_hours        : window to pay the booking fee before auto-cancel
 *   - cancel_balance_on        : when an unpaid balance auto-cancels ('due_date'|'check_in')
 *   - refund_policy            : extra refund terms the tenant appends to the platform default
 *
 * bookings (idempotency + snapshotted deadlines):
 *   - fee_due_at                    : booking-fee payment deadline (created_at + fee_payment_hours)
 *   - fee_reminder_sent_at          : guard so the fee chase fires once
 *   - full_payment_reminder_sent_at : guard so the balance reminder fires once
 *
 * Column adds are guarded with hasColumn() so the migration is safe to run on
 * dev databases that already carry some of these columns from earlier work.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'full_payment_days_before')) {
                $table->unsignedSmallInteger('full_payment_days_before')->default(7)->after('default_locale');
            }
            if (! Schema::hasColumn('tenants', 'fee_payment_hours')) {
                $table->unsignedSmallInteger('fee_payment_hours')->default(24)->after('full_payment_days_before');
            }
            if (! Schema::hasColumn('tenants', 'cancel_balance_on')) {
                $table->string('cancel_balance_on', 16)->default('check_in')->after('fee_payment_hours');
            }
            if (! Schema::hasColumn('tenants', 'refund_policy')) {
                $table->text('refund_policy')->nullable()->after('cancel_balance_on');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'fee_due_at')) {
                $table->timestamp('fee_due_at')->nullable()->after('balance_due_at');
            }
            if (! Schema::hasColumn('bookings', 'fee_reminder_sent_at')) {
                $table->timestamp('fee_reminder_sent_at')->nullable()->after('full_payment_reminder_at');
            }
            if (! Schema::hasColumn('bookings', 'full_payment_reminder_sent_at')) {
                $table->timestamp('full_payment_reminder_sent_at')->nullable()->after('fee_reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['fee_payment_hours', 'cancel_balance_on', 'refund_policy']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['fee_due_at', 'fee_reminder_sent_at']);
        });
    }
};
