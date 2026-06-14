<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-tenant "checkout reminder" config — an auto WhatsApp message
 * sent X hours before a guest's checkout with the host's checkout guidelines
 * (clean before you leave, take out the rubbish, lock up, etc.) — plus a
 * per-booking guard column so each reminder fires at most once.
 *
 * Idempotent (hasColumn guards) + MySQL/SQLite compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'checkout_reminder_enabled')) {
                $table->boolean('checkout_reminder_enabled')->default(true)->after('refund_policy');
            }
            if (! Schema::hasColumn('tenants', 'checkout_reminder_hours')) {
                $table->unsignedSmallInteger('checkout_reminder_hours')->nullable()->after('checkout_reminder_enabled');
            }
            if (! Schema::hasColumn('tenants', 'checkout_reminder_message')) {
                $table->text('checkout_reminder_message')->nullable()->after('checkout_reminder_hours');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'checkout_reminder_sent_at')) {
                $table->timestamp('checkout_reminder_sent_at')->nullable()->after('checkin_instructions_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['checkout_reminder_enabled', 'checkout_reminder_hours', 'checkout_reminder_message'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'checkout_reminder_sent_at')) {
                $table->dropColumn('checkout_reminder_sent_at');
            }
        });
    }
};
