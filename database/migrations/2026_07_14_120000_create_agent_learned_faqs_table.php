<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suggestions the AI agent distils from real WhatsApp conversations — the
 * "learns from day-to-day chats" pipeline. A weekly job reads recent
 * transcripts and proposes:
 *   - recurring : a question guests keep asking that has a clear answer
 *                 (the assistant/owner already answered it or it's grounded
 *                 in tenant data) → suggested_answer filled.
 *   - gap       : a question the agent couldn't answer / escalated on →
 *                 suggested_answer null ("needs your answer").
 *
 * These are ONLY suggestions. Nothing reaches a guest until the host approves
 * one on /dashboard/integrations/agent — approval copies the pair into the
 * tenant's training_qa FAQ (source='custom'), which the SystemPromptBuilder
 * already injects. So the agent never silently changes what it tells guests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_learned_faqs')) {
            return;
        }

        Schema::create('agent_learned_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('question', 400);
            $table->text('suggested_answer')->nullable(); // null for a 'gap'

            // recurring | gap
            $table->string('kind', 16)->default('recurring')->index();
            // pending | approved | dismissed
            $table->string('status', 16)->default('pending')->index();

            // How many conversations asked it (best-effort from the distiller).
            $table->unsignedInteger('occurrences')->default(1);
            // 1–2 real (PII-scrubbed) phrasings, so the host recognises it.
            $table->json('example_phrases')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_learned_faqs');
    }
};
