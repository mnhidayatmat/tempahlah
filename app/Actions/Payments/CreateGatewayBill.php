<?php

namespace App\Actions\Payments;

use App\Models\Booking;
use App\Models\TenantIntegration;

/**
 * Gateway-agnostic bill creator. Resolves the tenant's active payment gateway
 * (Toyyibpay or Billplz) and delegates to the matching action, returning the
 * exact same shape either way:
 *   ['payment' => Payment, 'payment_url' => string, 'bill_code' => string, 'reused' => bool]
 *
 * Selection rule (a tenant normally enables just one):
 *   - Only one gateway enabled  → use it.
 *   - Both enabled              → Billplz wins only if the tenant flagged it as
 *                                 primary in its config; otherwise Toyyibpay
 *                                 stays the default (backward-compatible with
 *                                 every existing tenant).
 *   - None enabled              → resolveProvider() returns null; callers pre-
 *                                 check gatewayConfigured() and fall back to a
 *                                 WhatsApp deeplink.
 */
class CreateGatewayBill
{
    public function __construct(
        protected CreateToyyibpayBill $toyyibpay,
        protected CreateBillplzBill $billplz,
    ) {}

    /**
     * @param  string  $type  Payment::TYPE_DEPOSIT | TYPE_BALANCE | TYPE_FULL
     * @return array{payment: \App\Models\Payment, payment_url: string, bill_code: string, reused: bool}
     */
    public function execute(Booking $booking, string $type, float $amount): array
    {
        $provider = $this->resolveProvider($booking->tenant_id);

        return match ($provider) {
            'billplz' => $this->billplz->execute($booking, $type, $amount),
            // Default (and 'toyyibpay') keeps the incumbent gateway. If no
            // gateway is configured, ToyyibpayClient::forTenant throws
            // notConfigured — callers guard with gatewayConfigured() first.
            default   => $this->toyyibpay->execute($booking, $type, $amount),
        };
    }

    /**
     * Which gateway should this tenant bill through? Returns 'toyyibpay',
     * 'billplz', or null when neither is enabled.
     */
    public function resolveProvider(int $tenantId): ?string
    {
        $integrations = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('provider', [
                TenantIntegration::PROVIDER_TOYYIBPAY,
                TenantIntegration::PROVIDER_BILLPLZ,
            ])
            ->where('enabled', true)
            ->get()
            ->keyBy('provider');

        $toyyib = $integrations->get(TenantIntegration::PROVIDER_TOYYIBPAY);
        $billplz = $integrations->get(TenantIntegration::PROVIDER_BILLPLZ);

        $billplzPrimary = $billplz && (bool) ($billplz->config['is_primary'] ?? false);

        if ($billplz && ($billplzPrimary || ! $toyyib)) {
            return 'billplz';
        }
        if ($toyyib) {
            return 'toyyibpay';
        }
        if ($billplz) {
            return 'billplz';
        }

        return null;
    }

    /**
     * True when the tenant has at least one payment gateway enabled.
     */
    public function gatewayConfigured(int $tenantId): bool
    {
        return $this->resolveProvider($tenantId) !== null;
    }
}
