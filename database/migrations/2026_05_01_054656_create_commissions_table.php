<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_rate', 5, 4);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('gateway_fee', 12, 2)->default(0);
            $table->decimal('payout_amount', 12, 2);

            $table->string('status')->default('pending');
            $table->foreignId('payout_id')->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['payout_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
