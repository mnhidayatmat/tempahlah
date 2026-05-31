<?php

namespace App\Jobs\Agent;

use App\Models\AgentConversation;
use App\Models\Booking;
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

            // Stale-inbound guard: never reply to an inbound that arrived more
            // than N minutes ago. This protects guests from "out of the blue"
            // replies caused by queue backlogs, sidecar restarts, or any other
            // delay between webhook receipt and job processing. If a customer
            // messaged hours ago and gave up, we should NOT suddenly ping them
            // with a robot reply once the queue catches up.
            $maxAgeMinutes = (int) config('agent.max_inbound_age_minutes', 5);
            $ageMinutes = $inbound->created_at?->diffInMinutes(now()) ?? 0;
            if ($ageMinutes > $maxAgeMinutes) {
                Log::info('Agent reply skipped: inbound too old', [
                    'tenant_id'      => $this->tenantId,
                    'inbound_msg_id' => $this->inboundMessageId,
                    'inbound_phone'  => $inbound->recipient_phone,
                    'age_minutes'    => $ageMinutes,
                    'cap_minutes'    => $maxAgeMinutes,
                ]);
                return;
            }

            // Existing-guest guard: if this phone already has a pending /
            // confirmed / checked-in booking with this tenant, leave them
            // to the human owner. The AI brochure voice intruding mid-trip
            // is exactly the "robot suddenly replied" complaint we want
            // to prevent. New prospects (no booking yet, or only cancelled
            // / no-show / checked-out bookings) still get the agent.
            $phone = $inbound->recipient_phone;
            if ($phone && $this->hasActiveBooking($this->tenantId, $phone)) {
                Log::info('Agent reply skipped: phone has active booking — handing off to human', [
                    'tenant_id'     => $this->tenantId,
                    'inbound_phone' => $phone,
                ]);
                return;
            }

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

    /**
     * Does this phone have any active or upcoming booking for this tenant?
     *
     * "Active" = pending / confirmed / checked-in. Bookings that are
     * cancelled, no_show, or checked_out are explicitly NOT active —
     * those phones are treated as new prospects and the agent engages.
     *
     * Matches on `booking_guests.phone` (which captures every guest on the
     * booking, including the lead) as well as the linked `users.phone`
     * (for bookings created via the marketplace flow where the lead is a
     * registered user).
     */
    private function hasActiveBooking(int $tenantId, string $phone): bool
    {
        $activeStatuses = [
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_CHECKED_IN,
        ];

        return Booking::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $activeStatuses)
            ->where(function ($q) use ($phone) {
                $q->whereHas('bookingGuests', fn ($bg) => $bg->where('phone', $phone))
                  ->orWhereHas('guest', fn ($u) => $u->where('phone', $phone));
            })
            ->exists();
    }
}
