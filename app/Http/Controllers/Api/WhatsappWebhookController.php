<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Agent\ProcessAgentReply;
use App\Models\AgentConversation;
use App\Models\AgentUsageDaily;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\Agent\AgentSettings;
use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Receives signed callbacks from the Node sidecar.
 *
 * Auth: App\Http\Middleware\VerifyWhatsappWebhook (HMAC).
 *
 * Events handled:
 *   session.qr            — sidecar generated a new QR for a tenant
 *   session.connected     — scan succeeded, session live
 *   session.banned        — WA closed the connection (logged out / forbidden)
 *   session.disconnected  — clean logout we initiated
 *   session.error         — transient failure, sidecar will retry
 *   message.inbound       — guest replied; check for opt-out keyword
 */
class WhatsappWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $event = $request->input('event');
        $payload = $request->input('payload', []);
        $tenantId = (int) ($payload['tenantId'] ?? 0);

        if (! $tenantId) {
            return response()->json(['ok' => false, 'error' => 'tenantId missing'], 422);
        }

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        if (! $tenant) {
            return response()->json(['ok' => false, 'error' => 'unknown tenant'], 404);
        }

        $session = WhatsappSession::withoutGlobalScopes()
            ->firstOrCreate(['tenant_id' => $tenantId]);

        match ($event) {
            'session.qr'            => $this->onQr($session, $payload),
            'session.connected'     => $this->onConnected($session, $payload),
            'session.banned'        => $this->onBanned($session, $payload),
            'session.disconnected'  => $this->onDisconnected($session, $payload),
            'session.error'         => $this->onError($session, $payload),
            'message.inbound'       => $this->onInbound($tenant, $session, $payload),
            default => Log::info('Unknown WA webhook event', ['event' => $event]),
        };

        return response()->json(['ok' => true]);
    }

    protected function onQr(WhatsappSession $session, array $payload): void
    {
        $session->update([
            'status' => WhatsappSession::STATUS_QR_PENDING,
            'qr_payload' => $payload['qrDataUrl'] ?? null,
            'qr_generated_at' => $payload['generatedAt'] ?? now(),
            'last_error' => null,
        ]);
    }

    protected function onConnected(WhatsappSession $session, array $payload): void
    {
        $session->update([
            'status' => WhatsappSession::STATUS_CONNECTED,
            'phone_e164' => PhoneNumber::normalize($payload['phone'] ?? null) ?? $payload['phone'] ?? null,
            'push_name' => $payload['pushName'] ?? null,
            'qr_payload' => null,
            'qr_generated_at' => null,
            'connected_at' => now(),
            'disconnected_at' => null,
            'last_seen_at' => now(),
            'last_error' => null,
        ]);
    }

    protected function onBanned(WhatsappSession $session, array $payload): void
    {
        $session->update([
            'status' => WhatsappSession::STATUS_BANNED,
            'qr_payload' => null,
            'disconnected_at' => now(),
            'last_error' => $payload['reason'] ?? 'banned',
        ]);
    }

    protected function onDisconnected(WhatsappSession $session, array $payload): void
    {
        $session->update([
            'status' => WhatsappSession::STATUS_DISCONNECTED,
            'qr_payload' => null,
            'disconnected_at' => now(),
            'last_error' => $payload['reason'] ?? null,
        ]);
    }

    protected function onError(WhatsappSession $session, array $payload): void
    {
        $session->update([
            'status' => WhatsappSession::STATUS_ERROR,
            'last_error' => $payload['error'] ?? 'unknown',
        ]);
    }

    protected function onInbound(Tenant $tenant, WhatsappSession $session, array $payload): void
    {
        $fromPhone = PhoneNumber::normalize($payload['fromPhone'] ?? null);
        $body = $payload['body'] ?? '';

        if (! $fromPhone || ! $body) return;

        // Log the inbound message.
        $inboundMsg = WhatsappMessage::create([
            'tenant_id' => $tenant->id,
            'direction' => WhatsappMessage::DIRECTION_IN,
            'kind' => WhatsappMessage::KIND_INBOUND,
            'recipient_phone' => $fromPhone,
            'body' => $body,
            'status' => WhatsappMessage::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        // Opt-out keyword detection (must run BEFORE the agent so a STOP
        // reply doesn't trigger an auto-response).
        $keywords = $session->pref('opt_out_keywords') ?? [];
        $haystack = mb_strtoupper(trim($body));
        foreach ($keywords as $kw) {
            if (mb_strtoupper(trim($kw)) === $haystack) {
                $this->markGuestOptedOut($tenant, $fromPhone);
                return;
            }
        }

        $this->maybeDispatchAgent($tenant, $fromPhone, $body, $inboundMsg);
    }

    /**
     * Hand off to the AI agent if the tenant has it enabled and we're
     * within their daily cap. All checks are silent — when something
     * blocks the agent (cap reached, escalated, off-hours) we simply
     * don't reply.
     */
    protected function maybeDispatchAgent(
        Tenant $tenant,
        string $fromPhone,
        string $body,
        WhatsappMessage $inboundMsg,
    ): void {
        if (! Feature::for($tenant)->active('ai_agent')) return;

        $settings = AgentSettings::forTenant($tenant);
        if (! $settings->enabled) return;

        if ($this->guestOptedOut($tenant, $fromPhone)) return;

        // Conversation (find-or-create), bookkeeping.
        $convo = AgentConversation::withoutGlobalScopes()
            ->firstOrNew(['tenant_id' => $tenant->id, 'guest_phone' => $fromPhone]);
        if (! $convo->exists) {
            $convo->status = AgentConversation::STATUS_ACTIVE;
        }
        $convo->last_inbound_at = now();
        $convo->message_count = ($convo->message_count ?? 0) + 1;
        $convo->save();

        if (! $convo->isActive()) return;

        // Out of business hours → optional one-off auto-reply, no LLM.
        if (! $settings->withinBusinessHours()) {
            $msg = $this->detectLocaleQuick($body) === 'ms'
                ? str_replace('{{tenant_name}}', $tenant->business_name ?? '', $settings->outOfHoursBm)
                : str_replace('{{tenant_name}}', $tenant->business_name ?? '', $settings->outOfHoursEn);
            if (trim($msg) !== '') {
                \App\Services\WhatsApp\WhatsappMessenger::dispatchAgentReply(
                    tenant: $tenant,
                    recipientPhone: $fromPhone,
                    body: $msg,
                );
            }
            return;
        }

        // Daily cap (platform belt + tenant suspenders).
        $usage = AgentUsageDaily::todayFor($tenant->id);
        if (($usage->reply_count ?? 0) >= $settings->dailyCap) return;
        $usage->increment('inbound_count');

        // Per-phone soft cap.
        $perPhoneCap = (int) config('agent.per_phone_daily_cap', 30);
        $todayCount = WhatsappMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('recipient_phone', $fromPhone)
            ->where('kind', WhatsappMessage::KIND_AGENT_REPLY)
            ->whereDate('created_at', now()->toDateString())
            ->count();
        if ($todayCount >= $perPhoneCap) return;

        ProcessAgentReply::dispatch($tenant->id, $convo->id, $inboundMsg->id);
    }

    protected function guestOptedOut(Tenant $tenant, string $phone): bool
    {
        $user = User::query()->where('phone', $phone)->first();
        if (! $user) return false;
        $list = (array) data_get($user->meta, 'wa_opted_out_for_tenants', []);
        return in_array($tenant->id, $list, true);
    }

    protected function detectLocaleQuick(string $body): string
    {
        $text = mb_strtolower($body);
        foreach (['nak','boleh','tempah','malam','bilik','salam','berapa','tarikh','tuan'] as $w) {
            if (str_contains($text, $w)) return 'ms';
        }
        return 'en';
    }

    protected function markGuestOptedOut(Tenant $tenant, string $phone): void
    {
        $user = User::query()->where('phone', $phone)->first();
        if (! $user) return;
        $meta = $user->meta ?? [];
        $meta['wa_opted_out_for_tenants'] = array_values(array_unique([
            ...($meta['wa_opted_out_for_tenants'] ?? []),
            $tenant->id,
        ]));
        $user->forceFill(['meta' => $meta])->save();
    }
}
