<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping auto-complete + typical-cost assumption (host request).
 *
 *  - auto_complete_housekeeping: master toggle for the hourly
 *    `housekeeping:auto-complete` command that auto-starts/finishes cleaning +
 *    laundry tasks the host forgot to tick. Nullable; accessor defaults it ON.
 *  - default_cleaning_cost / default_laundry_cost: the tenant's typical price,
 *    applied to a task on completion when no cost was entered. Nullable; the
 *    accessor falls back to the platform default when unset.
 *
 * Idempotent (hasColumn guards); MySQL + SQLite safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $t) {
            if (! Schema::hasColumn('tenants', 'auto_complete_housekeeping')) {
                $t->boolean('auto_complete_housekeeping')->nullable()->after('auto_housekeeping');
            }
            if (! Schema::hasColumn('tenants', 'default_cleaning_cost')) {
                $t->decimal('default_cleaning_cost', 10, 2)->nullable()->after('auto_complete_housekeeping');
            }
            if (! Schema::hasColumn('tenants', 'default_laundry_cost')) {
                $t->decimal('default_laundry_cost', 10, 2)->nullable()->after('default_cleaning_cost');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $t) {
            foreach (['auto_complete_housekeeping', 'default_cleaning_cost', 'default_laundry_cost'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
