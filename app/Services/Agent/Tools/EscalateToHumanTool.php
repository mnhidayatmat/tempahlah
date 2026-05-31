<?php

namespace App\Services\Agent\Tools;

use App\Models\AgentConversation;
use App\Services\Agent\Llm\ToolDefinition;
use App\Services\WhatsApp\WhatsappMessenger;

class EscalateToHumanTool extends Tool
{
    public function name(): string { return 'escalate_to_human'; }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: 'Hand the conversation off to a human. Use this when the guest: asks for the manager / owner / "tuan rumah", files a complaint, asks for a refund, mentions a safety emergency, or asks something you cannot confidently answer from the tools. After this is called, you will go silent on this conversation until the owner re-activates it.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string', 'description' => 'Short reason — shown to the owner.'],
                ],
                'required' => ['reason'],
            ],
        );
    }

    public function execute(array $args, ToolContext $ctx): array
    {
        $reason = trim((string) ($args['reason'] ?? 'manual escalation'));

        $ctx->conversation->update([
            'status'             => AgentConversation::STATUS_ESCALATED,
            'escalated_at'       => now(),
            'escalation_reason'  => $reason,
        ]);

        // Optionally ping the owner. Best-effort — silent failure if the
        // configured handoff_phone isn't a booked-guest or session is down.
        $settings = app(\App\Services\Agent\AgentSettings::class)::forTenant($ctx->tenant);
        if ($settings->handoffPhone) {
            try {
                WhatsappMessenger::dispatchAgentReply(
                    tenant:         $ctx->tenant,
                    recipientPhone: $settings->handoffPhone,
                    body:           "⚠️ AI assistant escalated a conversation.\nGuest: {$ctx->conversation->guest_phone}\nReason: {$reason}",
                );
            } catch (\Throwable) {
                // ignore — the conversation status flip is the source of truth
            }
        }

        return [
            'escalated' => true,
            'reason'    => $reason,
            'message'   => $ctx->locale === 'ms'
                ? 'Sila tunggu sebentar — tuan rumah akan menyambung perbualan ini.'
                : "Got it — I'll get the owner to jump in for this one.",
        ];
    }
}
