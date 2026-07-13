<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace showcase ranking for the 3-tier model: Ultra (featured, 2) >
 * Pro (priority, 1) > Free (standard, 0). Persisted on the listing so search
 * ordering is a plain indexed ORDER BY — kept current by PublishListing on
 * publish/sync and by SubscriptionObserver on plan changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_listings', 'showcase_rank')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                $table->unsignedTinyInteger('showcase_rank')->default(0)->after('status')->index();
            });
        }

        // Backfill from each listing tenant's current tier.
        $listings = DB::table('marketplace_listings')->pluck('tenant_id', 'id');
        $ranks = [];
        foreach (\App\Models\Subscription::whereIn('tenant_id', $listings->unique()->values())->get() as $sub) {
            $ranks[$sub->tenant_id] = \App\Support\Billing\Plans::rank($sub->effectivePlanKey());
        }
        foreach ($listings as $id => $tenantId) {
            DB::table('marketplace_listings')
                ->where('id', $id)
                ->update(['showcase_rank' => $ranks[$tenantId] ?? 0]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_listings', 'showcase_rank')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                $table->dropColumn('showcase_rank');
            });
        }
    }
};
