<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-booking flat fee on properties (cleaning fee, service fee, etc.).
 *
 * Charged ONCE per booking — independent of nights, room count, or
 * pricing rules. Common Malaysian-homestay use cases:
 *   - Cleaning fee (yuran pembersihan) — RM 50–150
 *   - Service / handling fee — RM 20–50
 *   - Linen/towel fee — RM 30
 *
 * Both columns nullable so existing properties remain unaffected;
 * `booking_fee_amount=null` (or `0`) means "no fee" and the summary +
 * invoice omit the line entirely.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('booking_fee_amount', 10, 2)->nullable()->after('map_url');
            $table->string('booking_fee_label', 80)->nullable()->after('booking_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['booking_fee_amount', 'booking_fee_label']);
        });
    }
};
