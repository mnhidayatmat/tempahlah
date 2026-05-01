<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('slug')->unique();
            $table->string('title_bm');
            $table->string('title_en');
            $table->string('hero_photo_path')->nullable();
            $table->json('search_keywords')->nullable();

            $table->string('city');
            $table->string('state');
            $table->string('country', 2)->default('MY');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->decimal('base_price_min', 10, 2)->nullable();
            $table->decimal('base_price_max', 10, 2)->nullable();

            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->default(0);

            $table->boolean('is_featured')->default(false);
            $table->timestamp('featured_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index(['city', 'state', 'status']);
            $table->index(['status', 'is_featured', 'published_at']);
            $table->index('rating_avg');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
