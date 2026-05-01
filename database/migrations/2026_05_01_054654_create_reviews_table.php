<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->string('reviewer_type');
            $table->morphs('subject');

            $table->unsignedTinyInteger('rating_overall');
            $table->json('rating_breakdown')->nullable();
            $table->text('comment')->nullable();
            $table->text('public_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->boolean('is_published')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'is_published']);
            $table->index(['reviewer_type', 'rating_overall']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
