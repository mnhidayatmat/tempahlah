<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            // Free-form tag (kitchen / bedroom / bathroom / etc).
            // Nullable — existing rows stay untagged until the tenant labels them.
            $table->string('category', 40)->nullable()->after('caption_en');
            $table->index(['property_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
