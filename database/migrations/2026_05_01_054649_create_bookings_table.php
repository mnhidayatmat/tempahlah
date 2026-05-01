<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('reference')->unique();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('channel')->default('direct');
            $table->string('status')->default('pending');

            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);

            $table->string('currency', 3)->default('MYR');
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('sst_amount', 12, 2)->default(0);
            $table->decimal('tourism_tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->decimal('deposit_pct', 5, 2)->default(20);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->timestamp('deposit_paid_at')->nullable();
            $table->timestamp('balance_due_at')->nullable();
            $table->timestamp('balance_paid_at')->nullable();
            $table->date('full_payment_reminder_at')->nullable();

            $table->boolean('is_foreigner')->default(false);
            $table->decimal('commission_amount', 12, 2)->default(0);

            $table->text('special_requests')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_uid')->nullable();

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['property_id', 'check_in', 'check_out']);
            $table->index(['room_id', 'check_in', 'check_out']);
            $table->index(['channel', 'status']);
            $table->unique(['source_uid', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
