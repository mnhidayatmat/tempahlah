<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->cascadeOnDelete();

            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->default('manual');
            $table->string('source')->default('manual');
            $table->string('source_uid')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['property_id', 'starts_on', 'ends_on']);
            $table->index(['room_id', 'starts_on', 'ends_on']);
            $table->unique(['source', 'source_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_blocks');
    }
};
