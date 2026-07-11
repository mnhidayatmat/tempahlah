<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe recurring billing. A Stripe-managed subscription stores its Customer +
 * Subscription ids here; Stripe auto-charges each cycle and drives our state via
 * webhooks, so these subs are excluded from the Billplz pay-link cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('card_status');
            }
            if (! Schema::hasColumn('subscriptions', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            }
            if (! Schema::hasColumn('subscriptions', 'stripe_price_id')) {
                $table->string('stripe_price_id')->nullable()->after('stripe_subscription_id');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! $this->hasIndex('subscriptions_stripe_subscription_id_index')) {
                $table->index('stripe_subscription_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if ($this->hasIndex('subscriptions_stripe_subscription_id_index')) {
                $table->dropIndex('subscriptions_stripe_subscription_id_index');
            }
            foreach (['stripe_customer_id', 'stripe_subscription_id', 'stripe_price_id'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('subscriptions') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
