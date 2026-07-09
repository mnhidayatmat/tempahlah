<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-side bill for one subscription cycle (the tenant owes Tempahlah
 * RM 49). Distinct from `invoices`, which is the tenant's own document issued
 * to their guest for a booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_invoices')) {
            return;
        }

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();

            // Human-readable + used as the gateway's reference_1, so a callback
            // that loses the bill id can still resolve back to this row.
            $table->string('number')->unique();

            $table->string('status')->default('pending'); // pending|paid|failed|void
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('MYR');

            // The cycle this bill buys. On settlement the subscription's period
            // is advanced to exactly these dates.
            $table->date('period_start');
            $table->date('period_end');

            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('gateway_provider')->nullable();
            $table->string('gateway_bill_id')->nullable();
            $table->text('payment_url')->nullable();

            // Dunning: how many reminders have gone out, and when the last one did.
            $table->unsignedInteger('reminders_sent')->default(0);
            $table->timestamp('last_reminder_at')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'due_at']);
            $table->index('gateway_bill_id');

            // One open bill per cycle — the service reuses an existing pending
            // invoice rather than minting a second bill for the same period.
            $table->unique(['subscription_id', 'period_start'], 'sub_invoice_cycle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
