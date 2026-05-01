<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('disk')->default('s3');
            $table->string('caption_bm')->nullable();
            $table->string('caption_en')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_hero')->default(false);
            $table->timestamps();

            $table->index(['property_id', 'sort_order']);
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_photos');
    }
};
