<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('rule_type');
            $table->json('weekday_mask')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();

            $table->string('adjustment_type')->default('percent');
            $table->decimal('adjustment_value', 10, 4);

            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'active']);
            $table->index(['room_id', 'active']);
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
