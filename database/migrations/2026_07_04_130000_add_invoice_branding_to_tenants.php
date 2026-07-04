<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice / receipt branding: a tagline + address for the document header, a
 * display bank account number and payment QR image for the payment footer, and
 * a terms block. (logo_path, bank_name and bank_account_holder already exist on
 * tenants — reused here.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'invoice_tagline')) {
                $table->string('invoice_tagline', 160)->nullable()->after('logo_path');
            }
            if (! Schema::hasColumn('tenants', 'business_address')) {
                $table->string('business_address', 255)->nullable()->after('invoice_tagline');
            }
            if (! Schema::hasColumn('tenants', 'bank_account_number')) {
                $table->string('bank_account_number', 60)->nullable()->after('bank_account_holder');
            }
            if (! Schema::hasColumn('tenants', 'bank_qr_path')) {
                $table->string('bank_qr_path', 255)->nullable()->after('bank_account_number');
            }
            if (! Schema::hasColumn('tenants', 'invoice_terms')) {
                $table->text('invoice_terms')->nullable()->after('bank_qr_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach ([
                'invoice_tagline', 'business_address', 'bank_account_number',
                'bank_qr_path', 'invoice_terms',
            ] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
