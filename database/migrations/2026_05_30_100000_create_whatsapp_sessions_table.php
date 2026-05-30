<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant WhatsApp Web (Baileys) session state.
 *
 * One row per tenant. The Node sidecar owns the actual auth keys on disk
 * (round-tripped to Spaces under tempahlah/whatsapp-sessions/{tenant_id}/),
 * this table only mirrors the high-level lifecycle so Laravel can drive
 * the UI and enforce per-tenant policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            // disconnected -> qr_pending -> connecting -> connected
            //                          \-> expired (QR expired before scan)
            //                          \-> error
            // connected   -> disconnected (clean logout)
            //             -> banned       (sidecar saw a 401/403 from WA)
            //             -> error        (network / pairing problem, retryable)
            $table->string('status', 24)->default('disconnected')->index();

            $table->string('phone_e164', 24)->nullable();      // e.g. +60123456789
            $table->string('push_name', 120)->nullable();      // WA display name

            // Transient — sidecar refreshes ~every 20s while qr_pending.
            // Stored base64 PNG inline so the dashboard can render it.
            $table->text('qr_payload')->nullable();
            $table->timestamp('qr_generated_at')->nullable();

            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();

            // Per-session throughput safety net. Reset daily by scheduler.
            $table->unsignedInteger('daily_sent_count')->default(0);
            $table->timestamp('daily_count_reset_at')->nullable();

            // Spaces key for the encrypted Baileys auth blob.
            // Format: whatsapp-sessions/{tenant_id}/auth-{rand}.enc
            $table->string('session_blob_path')->nullable();

            // Per-tenant preferences (auto-send toggles, lead times, opt-out
            // keywords, etc.). Nullable until tenant first touches settings.
            //
            // Defaults applied at read-time:
            //   auto_confirmation:    true
            //   auto_reminder:        true
            //   auto_checkin:         true
            //   reminder_days_before: 3
            //   checkin_hours_before: 24
            //   rate_limit_seconds:   8     (min gap between sends)
            //   opt_out_keywords:     ['STOP','BERHENTI','UNSUBSCRIBE']
            $table->json('prefs')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
