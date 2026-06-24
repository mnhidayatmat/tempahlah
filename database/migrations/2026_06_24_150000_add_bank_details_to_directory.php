<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank payout details on the directory registries (cleaners / laundry vendors /
 * maintenance persons) so the host can pay them easily. The account number is
 * encrypted at the app layer (text column + encrypted cast). Idempotent.
 */
return new class extends Migration
{
    private array $tables = ['cleaners', 'laundry_vendors', 'maintenance_persons'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'bank_name')) {
                    $t->string('bank_name')->nullable()->after('email');
                }
                if (! Schema::hasColumn($table, 'bank_account_no')) {
                    $t->text('bank_account_no')->nullable()->after('bank_name');
                }
                if (! Schema::hasColumn($table, 'bank_account_holder')) {
                    $t->string('bank_account_holder')->nullable()->after('bank_account_no');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['bank_name', 'bank_account_no', 'bank_account_holder'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
