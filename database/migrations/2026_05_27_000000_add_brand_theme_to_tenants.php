<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
            $table->string('accent_color', 7)->nullable()->after('secondary_color');
        });

        // Backfill any tenants still on the pre-warm-light default sky blue so
        // they don't suddenly render blue chrome once theming becomes active.
        DB::table('tenants')
            ->where('primary_color', '#0ea5e9')
            ->update(['primary_color' => '#d97757']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['secondary_color', 'accent_color']);
        });
    }
};
