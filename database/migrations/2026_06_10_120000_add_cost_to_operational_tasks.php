<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-task operating cost so the dashboard can sum the current month's
 * cleaning + laundry + maintenance spend. Nullable; idempotent guards.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cleaning_tasks', 'laundry_tasks', 'maintenance_tickets'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'cost')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('cost', 10, 2)->nullable()->after('status');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['cleaning_tasks', 'laundry_tasks', 'maintenance_tickets'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'cost')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('cost');
                });
            }
        }
    }
};
