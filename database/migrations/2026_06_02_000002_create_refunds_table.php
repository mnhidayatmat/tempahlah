<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds — first-class tracked records for sending money back to a guest.
 *
 * Triggered automatically when a booking checks out successfully (host
 * stamps checked_out_at, system auto-creates a pending refund for the
 * deposit amount). Host then processes the actual transfer out-of-band
 * (Toyyibpay has no refund API in Malaysia — refunds happen via FPX /
 * DuitNow / bank transfer / cash) and updates the refund record with
 * the method + bank reference number for audit.
 *
 * Lifecycle: pending → processing → completed
 *            pending → cancelled  (host keeps deposit — damage, late cancel, etc.)
 *            processing → failed  (bank rejected the transfer)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // Original payment being refunded (typically the deposit /
            // booking fee). Nullable for ad-hoc / goodwill refunds that
            // don't tie to a specific payment row.
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MYR');

            // checkout_complete | cancellation | damage_deduction | goodwill | other
            $table->string('reason', 32)->default('checkout_complete');

            // pending | processing | completed | failed | cancelled
            $table->string('status', 16)->default('pending');

            // bank_transfer | duitnow | ewallet | cash | toyyibpay_dashboard
            $table->string('method', 32)->nullable();

            // Bank txn / FPX ref / DuitNow ref — for audit trail
            $table->string('external_reference', 120)->nullable();

            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['booking_id']);
            // Used by the auto-create idempotency check on checkout
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
