<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest bank details for a refund.
 *
 * After a successful check-out the host wants to return the deposit, but
 * Malaysian bank transfers need the guest's bank account. The host clicks
 * "Request bank details" → the guest gets a secure signed link to submit
 * their bank name / account number / account holder. Stored here (account
 * number encrypted at the app layer, per the KYC/bank security baseline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            if (! Schema::hasColumn('refunds', 'bank_name')) {
                $table->string('bank_name', 120)->nullable()->after('external_reference');
            }
            if (! Schema::hasColumn('refunds', 'bank_account_number')) {
                // Encrypted at the app layer (Eloquent `encrypted` cast) — the
                // ciphertext is long, so a text column.
                $table->text('bank_account_number')->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('refunds', 'bank_account_holder')) {
                $table->string('bank_account_holder', 160)->nullable()->after('bank_account_number');
            }
            if (! Schema::hasColumn('refunds', 'bank_details_requested_at')) {
                $table->timestamp('bank_details_requested_at')->nullable()->after('bank_account_holder');
            }
            if (! Schema::hasColumn('refunds', 'bank_details_submitted_at')) {
                $table->timestamp('bank_details_submitted_at')->nullable()->after('bank_details_requested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            foreach ([
                'bank_name', 'bank_account_number', 'bank_account_holder',
                'bank_details_requested_at', 'bank_details_submitted_at',
            ] as $col) {
                if (Schema::hasColumn('refunds', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
