<?php

namespace App\Jobs\Agent;

use App\Models\AgentConversation;
use App\Models\Tenant;
use App\Models\WhatsappMessage;
use App\Services\Agent\AgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Drive one inbound → reply round for the AI agent.
 *
 * Idempotent on (tenant_id, message_id) — duplicate webhook deliveries
 * never produce a double-reply because the first run takes a 5-minute
 * cache lock and the second is a no-op.
 *
 * Single try — if the LLM call genuinely fails, the AgentService inside
 * will surface a fallback message rather than the queue retrying and
 * spamming the guest.
 */
class ProcessAgentReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        public int $tenantId,
        public int $conversationId,
        public int $inboundMessageId,
    ) {
        $this->onQueue('agent');
    }

    public function handle(AgentService $agent): void
    {
        $lockKey = "agent:reply:{$this->tenantId}:{$this->inboundMessageId}";
        $lock = Cache::lock($lockKey, 300);
        if (! $lock->get()) {
            return; // another worker already handled this
        }

        try {
            $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);
            $convo  = AgentConversation::withoutGlobalScopes()->find($this->conversationId);
            $inbound = WhatsappMessage::withoutGlobalScopes()->find($this->inboundMessageId);

            if (! $tenant || ! $convo || ! $inbound) return;
            if (! $convo->isActive()) return;

            $agent->handle($tenant, $convo, $inbound);
        } catch (\Throwable $e) {
            Log::warning('Agent reply job failed', [
                'tenant_id' => $this->tenantId,
                'msg_id'    => $this->inboundMessageId,
                'err'       => $e->getMessage(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
