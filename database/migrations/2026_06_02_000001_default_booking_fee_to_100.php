<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default the per-property booking fee to RM 100 for every existing
 * property where it's null or 0. New properties also get RM 100 from
 * PropertyController::store(). This becomes the canonical "pay now"
 * amount surfaced on the public booking flow.
 *
 * No schema change — booking_fee_amount stays nullable so hosts can
 * still explicitly clear it back to "no fee" via the Pricing tab.
 * (When NULL/0, the public flow falls back to "no upfront" — but the
 * default of 100 means most tenants ship with a working pay-now value.)
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('properties')
            ->where(function ($q) {
                $q->whereNull('booking_fee_amount')
                  ->orWhere('booking_fee_amount', '<=', 0);
            })
            ->update(['booking_fee_amount' => 100.00]);
    }

    public function down(): void
    {
        // Non-reversible — we won't un-set fees on rollback because the
        // host may have edited them after this migration ran. Leave as-is.
    }
};
