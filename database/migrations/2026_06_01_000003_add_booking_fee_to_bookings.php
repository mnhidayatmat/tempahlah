<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the property's `booking_fee_amount` onto each booking at
 * creation time. Snapshotting (instead of joining property at read
 * time) keeps historical bookings stable when a host later changes
 * their cleaning fee — the price the guest agreed to never drifts.
 *
 * Default 0 so legacy rows pre-feature show "no fee" naturally.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('booking_fee_amount', 12, 2)->default(0)->after('tourism_tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('booking_fee_amount');
        });
    }
};
