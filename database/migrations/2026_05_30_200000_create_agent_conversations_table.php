<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (tenant, guest_phone) pair that the AI agent has handled.
 *
 * Tracks the thread state so we know whether the agent should still reply
 * (active), whether a human has taken over (escalated), or whether the
 * conversation has been muted/closed.
 *
 * Also drives the RecipientGuard exemption: an active row with a recent
 * last_inbound_at means we're inside the WhatsApp 24h customer-service
 * window and may send agent_reply messages without a BookingGuest row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('guest_phone', 24);     // E.164, e.g. +60123456789
            $table->string('guest_name', 120)->nullable();

            // active     — agent is in charge
            // escalated  — human took over, agent stays silent on this thread
            // paused     — tenant temporarily muted the agent for this thread
            // closed     — archive
            $table->string('status', 16)->default('active')->index();

            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);

            $table->timestamp('escalated_at')->nullable();
            $table->string('escalation_reason', 255)->nullable();

            // 'ms' | 'en' — auto-detected from recent inbound messages
            $table->string('locale', 4)->nullable();

            $table->text('summary')->nullable();   // optional running summary
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'guest_phone']);
            $table->index(['tenant_id', 'last_inbound_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversations');
    }
};
