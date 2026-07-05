<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homestays now auto-list on the marketplace by default once they go live.
 * `marketplace_opt_out` records that a host explicitly removed their homestay
 * from the marketplace, so it is NOT silently re-listed on the next edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (! Schema::hasColumn('properties', 'marketplace_opt_out')) {
                $table->boolean('marketplace_opt_out')->default(false)->after('marketplace_published_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'marketplace_opt_out')) {
                $table->dropColumn('marketplace_opt_out');
            }
        });
    }
};
