<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide settings the super-admin manages from the UI (e.g. Stripe keys).
 * Values are encrypted at the app layer — the same posture as tenant_integrations
 * config. Cross-tenant (no tenant_id): these are platform-level, not per-tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // Encrypted ciphertext is far longer than the plaintext → text.
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
