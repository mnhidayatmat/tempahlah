<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switch property_photos.disk default from the legacy 's3' value to 'spaces'
 * so any new PropertyPhoto row written without an explicit disk lands on the
 * Digital Ocean Spaces disk (which is itself the app-wide default filesystem).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            $table->string('disk')->default('spaces')->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            $table->string('disk')->default('s3')->change();
        });
    }
};
