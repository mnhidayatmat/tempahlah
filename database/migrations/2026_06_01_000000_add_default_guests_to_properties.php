<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-configurable default value for the "guests" stepper on the
 * public booking page ({slug}.tempahlah.com). When null, the public
 * page falls back to floor(sleeps_total / 2) per Property accessor.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_guests')->nullable()->after('toilets');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('default_guests');
        });
    }
};
