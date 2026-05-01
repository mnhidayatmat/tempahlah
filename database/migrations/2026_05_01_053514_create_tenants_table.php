<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('slug')->unique();
            $table->string('business_name');
            $table->string('business_email')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('ssm_number')->nullable();
            $table->string('motac_license')->nullable();
            $table->timestamp('motac_verified_at')->nullable();

            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('kyc_status')->default('pending');
            $table->string('kyc_documents_path')->nullable();
            $table->text('bank_account_encrypted')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_holder')->nullable();

            $table->string('status')->default('active');
            $table->boolean('sst_registered')->default(false);
            $table->decimal('sst_rate', 5, 4)->default(0.08);

            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#0ea5e9');
            $table->string('default_locale', 5)->default('ms');

            $table->timestamp('suspended_at')->nullable();
            $table->text('suspended_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'kyc_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
