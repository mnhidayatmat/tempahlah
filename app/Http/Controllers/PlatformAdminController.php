<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\StripeBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * In-app Platform Admin area (/dashboard/admin) — the "super tenant" view a
 * flagged user toggles into from the normal dashboard. Shows the Free vs Paid
 * subscriber breakdown + a cross-tenant list. Gated by the platform.admin
 * middleware. Tenant / Subscription models carry no tenant global scope, so
 * these queries see every tenant regardless of the signed-in user's own tenant.
 */
class PlatformAdminController extends Controller
{
    public function overview(Request $request)
    {
        return view('platform.overview', array_merge(
            $this->stats(),
            ['tenants' => $this->tenantList($request)],
        ));
    }

    /**
     * Secret settings (Stripe keys, etc.). Never emit a stored secret back to the
     * page — show a masked hint (last 4) so the admin can tell one is set without
     * exposing it.
     */
    public function settings(StripeBilling $stripe)
    {
        return view('platform.settings', [
            'stripe' => [
                'secret_key' => $this->mask(PlatformSetting::get('stripe.secret_key')),
                'webhook_secret' => $this->mask(PlatformSetting::get('stripe.webhook_secret')),
                // Non-secret — safe to show in full.
                'publishable_key' => (string) PlatformSetting::get('stripe.publishable_key', ''),
                'price_id' => (string) PlatformSetting::get('stripe.price_id', ''),
            ],
            'stripeEnabled' => $stripe->enabled(),
            'webhookUrl' => url('/api/webhooks/stripe'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'stripe_secret_key' => ['nullable', 'string', 'max:255'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'stripe_price_id' => ['nullable', 'string', 'max:120'],
        ]);

        // Secrets: only overwrite when a NEW value is typed (the field renders
        // blank), so re-saving the form doesn't wipe a key the admin left masked.
        foreach (['secret_key', 'webhook_secret'] as $secret) {
            $new = trim((string) ($validated["stripe_{$secret}"] ?? ''));
            if ($new !== '') {
                PlatformSetting::put("stripe.{$secret}", $new);
            }
        }

        // Non-secret: always set (empty clears them).
        PlatformSetting::put('stripe.publishable_key', trim((string) ($validated['stripe_publishable_key'] ?? '')));
        PlatformSetting::put('stripe.price_id', trim((string) ($validated['stripe_price_id'] ?? '')));

        return redirect()->route('platform.settings')->with('status', __('Stripe settings saved.'));
    }

    public function testStripe(StripeBilling $stripe)
    {
        $r = $stripe->testConnection();

        if (! $r['ok']) {
            return redirect()->route('platform.settings')
                ->with('error', __('Stripe test failed: :err', ['err' => $r['error'] ?? 'unknown error']));
        }

        $priceNote = $r['price_ok']
            ? __('Recurring price OK.')
            : __('⚠ Price id missing or not recurring — set a monthly price.');

        return redirect()->route('platform.settings')->with('status', __(
            '✓ Stripe connected: :acct (:country · :currency). :price',
            ['acct' => $r['account'], 'country' => strtoupper((string) $r['country']), 'currency' => strtoupper((string) $r['currency']), 'price' => $priceNote],
        ));
    }

    /** Masked hint for a stored secret: "sk_test_…mIc8kjh" style, never the whole thing. */
    protected function mask(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $prefix = Str::of($value)->before('_')->limit(8, '')->value();

        return ($prefix ? $prefix.'_' : '').'…'.substr($value, -4);
    }

    /**
     * Aggregate subscription counts + MRR in one grouped pass.
     *
     * @return array<string, mixed>
     */
    protected function stats(): array
    {
        $rows = Subscription::query()
            ->selectRaw('plan, status, COUNT(*) as c, COALESCE(SUM(monthly_amount), 0) as amt')
            ->groupBy('plan', 'status')
            ->get();

        $count = fn (?string $plan = null, ?string $status = null) => (int) $rows
            ->when($plan !== null, fn ($r) => $r->where('plan', $plan))
            ->when($status !== null, fn ($r) => $r->where('status', $status))
            ->sum('c');

        $totalTenants = Tenant::query()->count();
        $free       = $count(Subscription::PLAN_FREE);
        $paidActive = $count(Subscription::PLAN_PAID, Subscription::STATUS_ACTIVE);
        $trialing   = $count(null, Subscription::STATUS_TRIALING);
        $pastDue    = $count(null, Subscription::STATUS_PAST_DUE);
        $cancelled  = $count(null, Subscription::STATUS_CANCELLED);

        $mrr = (float) $rows
            ->where('plan', Subscription::PLAN_PAID)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->sum('amt');

        return [
            'totalTenants' => $totalTenants,
            'free'         => $free,
            'paidActive'   => $paidActive,
            'trialing'     => $trialing,
            'subscribed'   => $paidActive + $trialing,
            'pastDue'      => $pastDue,
            'cancelled'    => $cancelled,
            'mrr'          => $mrr,
        ];
    }

    /**
     * Paginated cross-tenant list with each tenant's plan + status. Optional
     * ?plan=free|paid and ?q= (business name / email) filters.
     */
    protected function tenantList(Request $request)
    {
        $plan = in_array($request->query('plan'), [Subscription::PLAN_FREE, Subscription::PLAN_PAID], true)
            ? $request->query('plan')
            : null;
        $q = trim((string) $request->query('q', ''));

        return Tenant::query()
            ->with(['subscription', 'owner:id,name,email'])
            ->when($plan, fn ($query) => $query->whereHas('subscription', fn ($s) => $s->where('plan', $plan)))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('business_name', 'like', "%{$q}%")
                ->orWhere('business_email', 'like', "%{$q}%")))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();
    }
}
