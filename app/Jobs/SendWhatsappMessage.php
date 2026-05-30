<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\WhatsApp\PhoneNumber;
use App\Services\WhatsApp\RecipientGuard;
use App\Services\WhatsApp\Sidecar\SidecarClient;
use App\Services\WhatsApp\Sidecar\SidecarException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Send a single WhatsApp message via the tenant's Baileys session.
 *
 * The job:
 *  1. Reloads the WhatsappMessage row to get the latest body/media
 *  2. Re-runs the RecipientGuard (caller may have queued before guard ran)
 *  3. Checks daily-cap + session connected state
 *  4. POSTs to the sidecar with the message
 *  5. Marks the row sent / rate_limited / failed and bumps daily count
 *
 * Retries are bounded — RATE_LIMITED retries with backoff, hard failures
 * (NOT_CONNECTED, GUARD) do not retry.
 */
class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $backoff = 30; // seconds, doubled per retry by Laravel

    public function __construct(public int $messageId) {}

    public function handle(SidecarClient $sidecar, RecipientGuard $guard): void
    {
        /** @var WhatsappMessage|null $msg */
        $msg = WhatsappMessage::withoutGlobalScopes()->find($this->messageId);
        if (! $msg) return;

        // Idempotency: if already sent or terminally failed, do nothing.
        if (in_array($msg->status, [
            WhatsappMessage::STATUS_SENT,
            WhatsappMessage::STATUS_DELIVERED,
            WhatsappMessage::STATUS_READ,
            WhatsappMessage::STATUS_BLOCKED,
        ], true)) {
            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->find($msg->tenant_id);
        if (! $tenant) {
            $this->markFailed($msg, 'tenant missing');
            return;
        }

        $session = WhatsappSession::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();
        if (! $session || ! $session->isConnected()) {
            $this->markFailed($msg, 'session not connected');
            return;
        }

        // Daily cap (Laravel-side belt + sidecar suspenders).
        $cap = (int) config('whatsapp.policy.daily_cap_per_session', 400);
        if ($session->daily_sent_count >= $cap) {
            $this->markFailed($msg, "daily cap reached ({$cap})");
            return;
        }

        // Recipient guard runs on every NON-test message. KIND_TEST is exempt
        // so the tenant can verify their integration on their own phone before
        // the first guest booking exists. To prevent abuse, cap test sends at
        // 10 per session per UTC day — that's plenty for legitimate testing
        // without enabling cold-spam through the test endpoint.
        if ($msg->kind !== WhatsappMessage::KIND_TEST) {
            if (! $guard->allows($tenant, $msg->recipient_phone)) {
                $msg->update([
                    'status' => WhatsappMessage::STATUS_BLOCKED,
                    'error' => 'recipient guard: '.$guard->reason(),
                ]);
                return;
            }
        } else {
            $todayTestCount = WhatsappMessage::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('kind', WhatsappMessage::KIND_TEST)
                ->whereIn('status', [
                    WhatsappMessage::STATUS_SENT,
                    WhatsappMessage::STATUS_DELIVERED,
                    WhatsappMessage::STATUS_READ,
                    WhatsappMessage::STATUS_SENDING,
                ])
                ->whereDate('created_at', now()->toDateString())
                ->count();
            if ($todayTestCount >= 10) {
                $msg->update([
                    'status' => WhatsappMessage::STATUS_BLOCKED,
                    'error' => 'test cap reached (10/day) — use a real booking to verify further',
                ]);
                return;
            }
        }

        $normalized = PhoneNumber::normalize($msg->recipient_phone);
        if (! $normalized) {
            $this->markFailed($msg, 'invalid recipient phone');
            return;
        }

        $msg->update([
            'status' => WhatsappMessage::STATUS_SENDING,
            'queued_at' => $msg->queued_at ?? now(),
        ]);

        try {
            $result = $sidecar->send(
                tenantId: $tenant->id,
                to: $normalized,
                body: $msg->body,
                media: $msg->media_url ? [
                    'url' => $msg->media_url,
                    'kind' => $msg->media_kind ?? 'pdf',
                    'filename' => basename(parse_url($msg->media_url, PHP_URL_PATH) ?? 'document.pdf'),
                ] : null,
            );

            $msg->update([
                'status' => WhatsappMessage::STATUS_SENT,
                'sidecar_message_id' => $result['wamid'] ?? null,
                'sent_at' => now(),
                'error' => null,
            ]);

            $session->increment('daily_sent_count');
            $session->update(['last_seen_at' => now()]);
        } catch (SidecarException $e) {
            if (str_contains($e->getMessage(), 'rate-limited')) {
                $msg->update([
                    'status' => WhatsappMessage::STATUS_RATE_LIMITED,
                    'error' => $e->getMessage(),
                ]);
                $this->release(max(5, (int) ceil(($e->retryAfterMs ?? 5000) / 1000)));
                return;
            }
            $this->markFailed($msg, $e->getMessage());
        } catch (Throwable $e) {
            Log::warning('WhatsApp send failed', [
                'msg_id' => $msg->id,
                'tenant_id' => $tenant->id,
                'err' => $e->getMessage(),
            ]);

            // Network-style errors: let Laravel retry until $tries.
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
            $this->markFailed($msg, $e->getMessage());
        }
    }

    protected function markFailed(WhatsappMessage $msg, string $reason): void
    {
        $msg->update([
            'status' => WhatsappMessage::STATUS_FAILED,
            'error' => $reason,
        ]);
    }
}
