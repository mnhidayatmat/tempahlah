<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalize the homestay filter facets onto marketplace_listings so search
 * stays a single indexed query: house_type (whole_house | per_room), the room
 * count, and the total sleeping capacity. (Amenities are filtered live via the
 * property_amenity pivot, so they're not denormalized here.) Populated by
 * PublishListing when a host opts a homestay into the marketplace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_listings', 'house_type')) {
                $table->string('house_type', 20)->nullable()->after('state');
            }
            if (! Schema::hasColumn('marketplace_listings', 'rooms_count')) {
                $table->unsignedSmallInteger('rooms_count')->default(0)->after('house_type');
            }
            if (! Schema::hasColumn('marketplace_listings', 'max_guests')) {
                $table->unsignedSmallInteger('max_guests')->default(0)->after('rooms_count');
            }
            $table->index(['status', 'house_type']);
            $table->index('rooms_count');
            $table->index('max_guests');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            foreach (['status_house_type_index' => ['status', 'house_type'], 'marketplace_listings_rooms_count_index' => 'rooms_count', 'marketplace_listings_max_guests_index' => 'max_guests'] as $name => $cols) {
                try { $table->dropIndex($cols); } catch (\Throwable $e) {}
            }
            foreach (['house_type', 'rooms_count', 'max_guests'] as $col) {
                if (Schema::hasColumn('marketplace_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
