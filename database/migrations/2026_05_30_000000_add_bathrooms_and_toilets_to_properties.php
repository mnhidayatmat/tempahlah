<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Bathrooms = full bathrooms (sink + toilet + shower/bath in one).
            // Toilets   = separate toilets-only (powder rooms, outdoor toilets).
            // Both default to 0 so existing rows pass.
            $table->unsignedTinyInteger('bathrooms')->default(0)->after('star_rating');
            $table->unsignedTinyInteger('toilets')->default(0)->after('bathrooms');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['bathrooms', 'toilets']);
        });
    }
};
