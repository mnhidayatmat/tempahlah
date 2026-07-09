<?php

namespace App\Actions\Payments;

use App\Models\Booking;
use App\Models\TenantIntegration;

/**
 * Gateway-agnostic bill creator. Resolves the tenant's active payment gateway
 * (Toyyibpay, Billplz or SecurePay) and delegates to the matching action,
 * returning the exact same shape whichever one runs:
 *   ['payment' => Payment, 'payment_url' => string, 'bill_code' => string, 'reused' => bool]
 *
 * Selection rule (a tenant normally enables just one):
 *   - Only one gateway enabled  → use it.
 *   - Several enabled           → the one the tenant ticked as primary wins.
 *                                 With no primary ticked, Toyyibpay stays the
 *                                 default, then Billplz, then SecurePay — so
 *                                 every existing tenant keeps billing through
 *                                 exactly the gateway it does today.
 *   - None enabled              → resolveProvider() returns null; callers pre-
 *                                 check gatewayConfigured() and fall back to a
 *                                 WhatsApp deeplink.
 */
class CreateGatewayBill
{
    /**
     * Fallback precedence when the tenant enabled several gateways but ticked
     * none as primary. Toyyibpay leads for backward compatibility.
     */
    protected const PRECEDENCE = [
        TenantIntegration::PROVIDER_TOYYIBPAY,
        TenantIntegration::PROVIDER_BILLPLZ,
        TenantIntegration::PROVIDER_SECUREPAY,
    ];

    public function __construct(
        protected CreateToyyibpayBill $toyyibpay,
        protected CreateBillplzBill $billplz,
        protected CreateSecurePayBill $securepay,
    ) {}

    /**
     * @param  string  $type  Payment::TYPE_DEPOSIT | TYPE_BALANCE | TYPE_FULL
     * @return array{payment: \App\Models\Payment, payment_url: string, bill_code: string, reused: bool}
     */
    public function execute(Booking $booking, string $type, float $amount): array
    {
        $provider = $this->resolveProvider($booking->tenant_id);

        return match ($provider) {
            'billplz'  => $this->billplz->execute($booking, $type, $amount),
            'securepay' => $this->securepay->execute($booking, $type, $amount),
            // Default (and 'toyyibpay') keeps the incumbent gateway. If no
            // gateway is configured, ToyyibpayClient::forTenant throws
            // notConfigured — callers guard with gatewayConfigured() first.
            default    => $this->toyyibpay->execute($booking, $type, $amount),
        };
    }

    /**
     * Which gateway should this tenant bill through? Returns 'toyyibpay',
     * 'billplz', 'securepay', or null when none is enabled.
     */
    public function resolveProvider(int $tenantId): ?string
    {
        $integrations = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('provider', self::PRECEDENCE)
            ->where('enabled', true)
            ->get()
            ->keyBy('provider');

        if ($integrations->isEmpty()) {
            return null;
        }

        // A single enabled gateway needs no tie-break.
        if ($integrations->count() === 1) {
            return (string) $integrations->keys()->first();
        }

        // Explicit primary wins, scanned in precedence order so two tenants who
        // both tick "primary" still resolve deterministically.
        foreach (self::PRECEDENCE as $provider) {
            $row = $integrations->get($provider);
            if ($row && (bool) ($row->config['is_primary'] ?? false)) {
                return $provider;
            }
        }

        foreach (self::PRECEDENCE as $provider) {
            if ($integrations->has($provider)) {
                return $provider;
            }
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
