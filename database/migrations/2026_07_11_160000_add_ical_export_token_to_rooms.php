<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rooms', 'ical_export_token')) {
            Schema::table('rooms', function (Blueprint $table) {
                // Unguessable, rotatable token that addresses this room's public
                // iCal busy-feed (GET /calendar/{token}.ics). Generated lazily
                // the first time the host opens Channel sync. Rotatable so a
                // leaked feed URL can be revoked. Nullable until first use.
                $table->string('ical_export_token', 40)->nullable()->unique()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rooms', 'ical_export_token')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('ical_export_token');
            });
        }
    }
};
