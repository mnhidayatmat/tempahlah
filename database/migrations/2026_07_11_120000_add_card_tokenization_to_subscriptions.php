<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billplz card auto-renew (Tokenization). One tokenized Visa/Mastercard per
 * subscription, so the daily bill-cycle can charge it instead of emailing a
 * pay-link. The token is the credential that charges money — stored encrypted
 * at the app layer, same as every other secret (KYC, gateway keys).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Tenant opted into automatic monthly charging of their saved card.
            if (! Schema::hasColumn('subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)->after('billing_method');
            }
            // Billplz card id (the {card_id} in POST /v4/bills/{bill}/charge).
            if (! Schema::hasColumn('subscriptions', 'card_id')) {
                $table->string('card_id')->nullable()->after('auto_renew');
            }
            // The charge token. `text` because the encrypted ciphertext is far
            // longer than the raw token, and encrypted values can't be indexed.
            if (! Schema::hasColumn('subscriptions', 'card_token')) {
                $table->text('card_token')->nullable()->after('card_id');
            }
            // Display only — last 4 + brand for the "Visa •••• 1234" panel.
            if (! Schema::hasColumn('subscriptions', 'card_last4')) {
                $table->string('card_last4', 4)->nullable()->after('card_token');
            }
            if (! Schema::hasColumn('subscriptions', 'card_brand')) {
                $table->string('card_brand')->nullable()->after('card_last4');
            }
            // active | pending | failed | deleted (Billplz card status values).
            if (! Schema::hasColumn('subscriptions', 'card_status')) {
                $table->string('card_status')->nullable()->after('card_brand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['auto_renew', 'card_id', 'card_token', 'card_last4', 'card_brand', 'card_status'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
