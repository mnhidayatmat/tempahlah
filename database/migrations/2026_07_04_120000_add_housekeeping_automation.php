<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-scheduled housekeeping SOP.
 *
 * cleaning_tasks: crew size + duration so an auto-generated turnover can carry
 * "2 cleaners · 2h" (tight turnover) vs "1 cleaner · 4h" (relaxed), plus an
 * `auto_generated` flag so the system never clobbers a host-edited task.
 *
 * tenants: a master toggle (default ON) to let a host turn the automation off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('cleaning_tasks', 'cleaners_required')) {
                $table->unsignedTinyInteger('cleaners_required')->default(1)->after('type');
            }
            if (! Schema::hasColumn('cleaning_tasks', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('cleaners_required');
            }
            if (! Schema::hasColumn('cleaning_tasks', 'auto_generated')) {
                $table->boolean('auto_generated')->default(false)->after('duration_minutes');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'auto_housekeeping')) {
                $table->boolean('auto_housekeeping')->default(true)->after('checkout_reminder_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_tasks', function (Blueprint $table) {
            foreach (['cleaners_required', 'duration_minutes', 'auto_generated'] as $col) {
                if (Schema::hasColumn('cleaning_tasks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'auto_housekeeping')) {
                $table->dropColumn('auto_housekeeping');
            }
        });
    }
};
