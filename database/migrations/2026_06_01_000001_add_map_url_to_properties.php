<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional pre-pinned map URL for the property. Lets the host paste an
 * exact Google Maps share-link (e.g. https://maps.app.goo.gl/abc123)
 * so the public booking page's "Direction" button opens the precise
 * pin instead of geocoding the free-text address (which can drift
 * by hundreds of metres for kampung addresses).
 *
 * Nullable — when blank the public page falls back to Maps-search by
 * the comma-joined address fields (line1, line2, postcode, city, state).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('map_url', 500)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('map_url');
        });
    }
};
