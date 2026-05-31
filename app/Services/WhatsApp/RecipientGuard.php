<?php

namespace App\Services\WhatsApp;

use App\Models\AgentConversation;
use App\Models\BookingGuest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Strict outbound policy: a tenant may only send WhatsApp messages to phone
 * numbers that appear in their own bookings.
 *
 * Why: cold messaging is the #1 ban trigger for unofficial WA gateways.
 * Restricting outbound to past/current guests:
 *   - keeps the platform defensible if Meta or PDPA officers come asking
 *   - drops ban risk by 1–2 orders of magnitude in practice
 *   - aligns with the actual product use case (booking comms, not marketing)
 *
 * The `recipient_guard` config key can be flipped to `permissive` for trusted
 * tenants (super-admin override path — wired in Phase 8 if needed).
 */
class RecipientGuard
{
    public function __construct(protected ?string $mode = null)
    {
        $this->mode = $mode ?? config('whatsapp.policy.recipient_guard', 'strict_guests');
    }

    /**
     * @param  string  $context  'booking_guest' (default) | 'agent_reply'
     * @return bool true if the tenant is allowed to send to $phone.
     */
    public function allows(Tenant $tenant, string $phone, string $context = 'booking_guest'): bool
    {
        $normalized = PhoneNumber::normalize($phone);
        if (! $normalized) return false;

        if ($this->mode === 'permissive') return true;

        // Agent replies are allowed when there's an active conversation
        // with a recent inbound from the guest — mirrors WhatsApp's
        // own 24h customer-service window rule.
        if ($context === 'agent_reply') {
            $exists = AgentConversation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('guest_phone', $normalized)
                ->where('status', AgentConversation::STATUS_ACTIVE)
                ->where('last_inbound_at', '>=', now()->subHours(24))
                ->exists();
            if ($exists) return true;
            // fall through to booked-guest check as belt-and-braces
        }

        // strict_guests: phone must match a BookingGuest tied to one of this
        // tenant's bookings.
        return BookingGuest::query()
            ->whereHas('booking', fn (Builder $q) => $q->where('tenant_id', $tenant->id))
            ->where(function (Builder $q) use ($normalized, $phone) {
                $q->where('phone', $normalized)
                  ->orWhere('phone', $phone)
                  ->orWhere('phone', ltrim($normalized, '+'));
            })
            ->exists();
    }

    public function reason(): string
    {
        return $this->mode === 'permissive'
            ? 'permissive mode'
            : 'recipient not a booked guest of this tenant';
    }
}
