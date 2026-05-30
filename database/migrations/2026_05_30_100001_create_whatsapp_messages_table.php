<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of every WhatsApp message the platform sends or receives via
 * a tenant's connected Baileys session. Used for:
 *  - the "Recent sends" panel on the tenant integrations page
 *  - opt-out detection on inbound messages
 *  - delivery troubleshooting + super-admin moderation
 *  - ban-risk analytics (per-tenant fail rate, cold-recipient ratio, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // guest user, if matched

            // out = platform -> guest. in = guest -> tenant (captured for opt-out).
            $table->string('direction', 4)->default('out')->index();

            // What triggered the send. 'inbound' for direction=in rows.
            //  manual | confirmation | reminder | checkin | invoice | receipt | test | inbound
            $table->string('kind', 24)->index();

            // E.164 (e.g. +60123456789). Always normalized before insert.
            $table->string('recipient_phone', 24)->index();
            $table->string('recipient_name', 120)->nullable();

            // Final rendered body. We store the text so tenants can audit
            // exactly what went out even after templates are edited later.
            $table->text('body');

            // Optional media (e.g. signed Spaces URL to an invoice PDF).
            $table->string('media_url', 1024)->nullable();
            $table->string('media_kind', 24)->nullable();  // pdf | image | doc

            // Future template registry FK — nullable now.
            $table->string('template_key', 64)->nullable();

            // queued -> sending -> sent -> delivered -> read
            //                          \-> failed
            //                          \-> blocked_by_guard
            //                          \-> rate_limited
            $table->string('status', 20)->default('queued')->index();

            // wamid from WhatsApp, e.g. "true_60123456789@c.us_3EB0..."
            $table->string('sidecar_message_id', 191)->nullable()->index();
            $table->text('error')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['booking_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
