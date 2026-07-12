<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'deposit_is_security')) {
            return;
        }

        // Make the security-deposit model the default: existing tenants that
        // never touched the setting (still on the shipped default of false) are
        // switched ON, and the column default flips to true so new tenants get
        // it too. The feature shipped moments earlier defaulting OFF, so no
        // tenant has deliberately chosen OFF yet — a blanket backfill is safe.
        DB::table('tenants')->where('deposit_is_security', false)->update(['deposit_is_security' => true]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('deposit_is_security')->default(true)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenants', 'deposit_is_security')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('deposit_is_security')->default(false)->change();
        });
    }
};
