<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description_bm')->nullable();
            $table->text('description_en')->nullable();

            $table->string('property_type')->default('homestay');
            $table->unsignedTinyInteger('star_rating')->nullable();

            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postcode', 10);
            $table->string('country', 2)->default('MY');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->time('check_in_time')->default('15:00');
            $table->time('check_out_time')->default('12:00');

            $table->text('house_rules')->nullable();
            $table->string('cancellation_policy')->default('flexible');

            $table->string('hero_photo_path')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('marketplace_enabled')->default(false);
            $table->timestamp('marketplace_published_at')->nullable();

            $table->string('custom_domain')->nullable()->unique();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['marketplace_enabled', 'status']);
            $table->index(['city', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
