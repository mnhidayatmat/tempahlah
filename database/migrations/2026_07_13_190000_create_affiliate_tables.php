<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate marketing — affiliates share a referral link, new host signups are
 * attributed to them, and they earn a recurring % of the referred tenant's
 * subscription payments. Platform-level tables (like subscription_invoices):
 * deliberately NOT tenant-scoped — an affiliate spans tenants, and commission
 * accrual runs from webhooks with no tenant context.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
                $table->id();
                // Set for host affiliates (the "Refer & Earn" page); null for
                // external affiliates (influencers/agencies) created by admin.
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                $table->string('name', 160);
                $table->string('email', 190)->nullable()->index();
                $table->string('phone', 40)->nullable();
                $table->string('code', 24)->unique();
                // Commission % of each subscription payment + how many months
                // (from the referred tenant's first paid conversion) it accrues.
                $table->decimal('rate', 5, 2)->default(20.00);
                $table->unsignedSmallInteger('duration_months')->default(12);
                $table->string('status', 20)->default('active')->index();
                // Payout bank details. Account number encrypted at the app layer
                // (Eloquent `encrypted` cast) per the platform security baseline.
                $table->string('bank_name', 120)->nullable();
                $table->string('bank_account_holder', 120)->nullable();
                $table->text('bank_account_no')->nullable();
                $table->string('notes', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('affiliate_referrals')) {
            Schema::create('affiliate_referrals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                // One affiliate per tenant, permanently — first attribution wins.
                $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
                // Stamped on the first commission; the duration_months earning
                // window runs from here, not from signup.
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('affiliate_commissions')) {
            Schema::create('affiliate_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                // Idempotency key — one commission per real payment, ever:
                // `subinv:{id}` (Billplz pay-link) / `stripe:{invoice_id}`.
                $table->string('source', 100)->unique();
                $table->string('description', 200)->nullable();
                $table->decimal('base_amount', 10, 2);
                $table->decimal('rate', 5, 2);
                $table->decimal('amount', 10, 2);
                // pending (30-day refund hold) | approved (payable) | paid | void
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('payout_ref', 120)->nullable();
                $table->timestamps();
                $table->index(['affiliate_id', 'status']);
            });
        }

        if (! Schema::hasTable('affiliate_visits')) {
            Schema::create('affiliate_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                // Daily click counter — cheap stats without a row per click.
                $table->date('date');
                $table->unsignedInteger('clicks')->default(0);
                $table->timestamps();
                $table->unique(['affiliate_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_visits');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliates');
    }
};
