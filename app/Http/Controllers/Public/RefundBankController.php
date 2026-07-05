<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Tenant;
use App\Support\Tenancy\BelongsToTenantScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Guest-facing "submit your bank account for a refund" page. Reached via a
 * signed magic-link the host sends from the booking's Refunds panel. The
 * `signed` route middleware verifies the HMAC — no password.
 */
class RefundBankController extends Controller
{
    public function show(Request $request, string $tenant_slug, string $refund): View
    {
        unset($tenant_slug); // resolved by the subdomain middleware

        [$tenant, $refundModel] = $this->resolve($request, $refund);

        return view('public-tenant.refund-bank', [
            'tenant' => $tenant,
            'refund' => $refundModel,
            'booking' => $refundModel->booking,
            'submitted' => $refundModel->bankDetailsSubmitted(),
        ]);
    }

    public function submit(Request $request, string $tenant_slug, string $refund): RedirectResponse
    {
        unset($tenant_slug);

        [$tenant, $refundModel] = $this->resolve($request, $refund);

        $validated = $request->validate([
            'bank_name'           => 'required|string|max:120',
            'bank_account_number' => 'required|string|max:60|regex:/^[0-9\s-]+$/',
            'bank_account_holder' => 'required|string|max:160',
        ], [
            'bank_account_number.regex' => __('Enter a valid account number (digits only).'),
        ]);

        $refundModel->forceFill([
            'bank_name'            => trim($validated['bank_name']),
            // Store digits only — strip spaces/dashes the guest may have typed.
            'bank_account_number'  => preg_replace('/[\s-]+/', '', $validated['bank_account_number']),
            'bank_account_holder'  => trim($validated['bank_account_holder']),
            'bank_details_submitted_at' => now(),
        ])->save();

        // Re-mint the signed link so the "thank you" state stays reachable on
        // reload (the original signature is still valid, but this is defensive).
        return redirect()
            ->to($refundModel->bankFormUrl())
            ->with('status', __('Thank you! Your bank details were sent to the host.'));
    }

    /**
     * Resolve the tenant (from the subdomain middleware) + the refund, scoped
     * to that tenant. 404 on any mismatch so a signed link for one tenant can't
     * read another's refund.
     *
     * @return array{0: Tenant, 1: Refund}
     */
    private function resolve(Request $request, string $refundPublicId): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');
        abort_unless($tenant, 404);

        $refund = Refund::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('public_id', $refundPublicId)
            ->with(['booking.property:id,name', 'booking.tenant', 'booking.bookingGuests'])
            ->firstOrFail();

        return [$tenant, $refund];
    }
}
