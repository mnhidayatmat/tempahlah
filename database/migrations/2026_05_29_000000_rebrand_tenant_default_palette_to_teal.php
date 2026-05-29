<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tempahlah rebrand: migrate tenants still on the legacy Claude-orange
        // default palette to the new teal/blue brand. Tenants who picked their
        // own custom palette via /dashboard/settings are unaffected.
        DB::table('tenants')
            ->where('primary_color', '#d97757')
            ->update(['primary_color' => '#2596c6']);

        DB::table('tenants')
            ->where('secondary_color', '#a8401e')
            ->update(['secondary_color' => '#2cb8c4']);

        DB::table('tenants')
            ->where('accent_color', '#d4a437')
            ->update(['accent_color' => '#e8b94a']);
    }

    public function down(): void
    {
        DB::table('tenants')
            ->where('primary_color', '#2596c6')
            ->update(['primary_color' => '#d97757']);

        DB::table('tenants')
            ->where('secondary_color', '#2cb8c4')
            ->update(['secondary_color' => '#a8401e']);

        DB::table('tenants')
            ->where('accent_color', '#e8b94a')
            ->update(['accent_color' => '#d4a437']);
    }
};
