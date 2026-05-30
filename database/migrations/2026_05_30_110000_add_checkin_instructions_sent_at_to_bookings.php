<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks when check-in instructions (email + WhatsApp) were sent for a
 * booking, so the scheduled DispatchCheckinInstructions command doesn't
 * re-send hourly during the [N, N+1) window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('checkin_instructions_sent_at')->nullable()->after('full_payment_reminder_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('checkin_instructions_sent_at');
        });
    }
};
