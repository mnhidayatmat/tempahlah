<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plan')->default('free');
            $table->string('status')->default('trialing');
            $table->string('billing_method')->default('manual');
            $table->decimal('monthly_amount', 8, 2)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['plan', 'status']);
            $table->index('current_period_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
