<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend guest_blacklist_entries for the cross-tenant customer blacklist:
 *
 *  - guest_phone / guest_email / guest_name  → denormalized identity snapshot
 *    taken when the report is filed. Cross-tenant matching keys off these
 *    (a returning guest at a different homestay may be a *different* User row —
 *    resolveGuest() dedupes on email only — so user_id alone under-matches).
 *  - reviewed_by_user_id  → the in-app Platform Admin (users.is_platform_admin)
 *    who verified the report. The original reviewed_by_admin_id FK targets the
 *    Filament super_admins table, which the in-app admin area doesn't use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_blacklist_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('guest_blacklist_entries', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('guest_user_id');
            }
            if (! Schema::hasColumn('guest_blacklist_entries', 'guest_phone')) {
                $table->string('guest_phone')->nullable()->after('guest_name');
            }
            if (! Schema::hasColumn('guest_blacklist_entries', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_phone');
            }
            if (! Schema::hasColumn('guest_blacklist_entries', 'reviewed_by_user_id')) {
                $table->foreignId('reviewed_by_user_id')->nullable()->after('reviewed_by_admin_id')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // Cross-tenant lookup hot path: match a booking's guest against verified
        // flags by phone. (guest_user_id + review_status already indexed.)
        Schema::table('guest_blacklist_entries', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('guest_blacklist_entries'))->pluck('name');
            if (! $indexes->contains('gbe_phone_status_idx')) {
                $table->index(['guest_phone', 'review_status'], 'gbe_phone_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guest_blacklist_entries', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('guest_blacklist_entries'))->pluck('name');
            if ($indexes->contains('gbe_phone_status_idx')) {
                $table->dropIndex('gbe_phone_status_idx');
            }
            if (Schema::hasColumn('guest_blacklist_entries', 'reviewed_by_user_id')) {
                $table->dropConstrainedForeignId('reviewed_by_user_id');
            }
            foreach (['guest_name', 'guest_phone', 'guest_email'] as $col) {
                if (Schema::hasColumn('guest_blacklist_entries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
