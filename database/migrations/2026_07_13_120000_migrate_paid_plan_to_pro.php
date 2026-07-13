<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 3-tier pricing (free / pro / ultra): the 2-tier era's 'paid' plan value
 * becomes 'pro'. Subscription::PLAN_PAID now aliases PLAN_PRO, so every code
 * path reads/writes 'pro' from the same deploy this runs in — no window where
 * the two disagree. Idempotent (re-running matches zero rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscriptions')
            ->where('plan', 'paid')
            ->update(['plan' => 'pro']);

        // Every Pennant flag definition changed from isPaid() to the per-plan
        // hasFeature() resolution in the same deploy, so cached flag rows are
        // stale. Flush them all — they lazily re-resolve on next read.
        if (Schema::hasTable('features')) {
            DB::table('features')->delete();
        }
    }

    public function down(): void
    {
        DB::table('subscriptions')
            ->where('plan', 'pro')
            ->update(['plan' => 'paid']);
    }
};
