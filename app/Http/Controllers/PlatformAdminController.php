<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\StripeBilling;
use App\Support\Tenancy\BelongsToTenantScope;
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
     * Edit a single tenant's business details + plan. Tenant / Subscription carry
     * no tenant global scope, so a platform admin can load any tenant.
     */
    public function editTenant(Tenant $tenant)
    {
        $tenant->loadMissing('subscription', 'owner');

        return view('platform.tenant-edit', [
            'tenant' => $tenant,
            'subscription' => $tenant->subscription,
            // Reflect actual access: comped / active / trialing / in-grace all read Pro.
            'currentPlan' => $tenant->subscription?->isPaid() ? 'pro' : 'free',
        ]);
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'business_name'  => ['required', 'string', 'max:160'],
            'business_email' => ['nullable', 'email', 'max:190'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'status'         => ['required', 'in:active,suspended'],
            'plan'           => ['required', 'in:free,pro'],
        ]);

        $tenant->fill([
            'business_name'  => $validated['business_name'],
            // nullable rules drop an absent key from $validated, so coalesce.
            'business_email' => ($validated['business_email'] ?? null) ?: null,
            'business_phone' => ($validated['business_phone'] ?? null) ?: null,
        ]);

        // Suspension bookkeeping — stamp/clear suspended_at so the tenant-resolver
        // middleware (which blocks suspended tenants) stays consistent.
        if ($validated['status'] === 'suspended') {
            if ($tenant->status !== 'suspended') {
                $tenant->suspended_at = now();
            }
            $tenant->status = 'suspended';
        } else {
            $tenant->status = 'active';
            $tenant->suspended_at = null;
            $tenant->suspended_reason = null;
        }

        $tenant->save();

        $this->applyPlan($tenant, $validated['plan']);

        return redirect()->route('platform.overview')
            ->with('status', __('Tenant ":name" updated.', ['name' => $tenant->business_name]));
    }

    /**
     * Force a tenant onto free or Pro. "Pro" here is a COMPLIMENTARY grant
     * (comped_at) — the designed way to hold every paid feature without running
     * the billing flow (partner, offline payment, staff). Writing through the
     * model fires SubscriptionObserver, which purges that tenant's cached Pennant
     * flags so features flip immediately.
     */
    private function applyPlan(Tenant $tenant, string $plan): void
    {
        $subscription = $tenant->subscription()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_method' => 'manual',
                'monthly_amount' => 0,
                'currency' => 'MYR',
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ],
        );

        if ($plan === 'pro') {
            $subscription->update([
                'plan' => Subscription::PLAN_PAID,
                'status' => Subscription::STATUS_ACTIVE,
                // Keep an existing comp date; otherwise stamp now.
                'comped_at' => $subscription->comped_at ?? now(),
                // 0 so this complimentary grant never inflates paying MRR.
                'monthly_amount' => 0,
                'grace_ends_at' => null,
                'trial_ends_at' => null,
                'cancelled_at' => null,
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ]);
        } else {
            $subscription->update([
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
                // Clear the comp so paid features actually switch off. trial_used_at
                // is deliberately preserved (no farming a fresh free trial).
                'comped_at' => null,
                'monthly_amount' => 0,
                'grace_ends_at' => null,
                'trial_ends_at' => null,
                'cancelled_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ]);
        }
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

    /**
     * Cross-tenant testimonial moderation. Guest testimonials auto-publish, so
     * this is where a super admin hides/removes spam, abuse, or a fake. Reviews
     * carry a tenant global scope; bypass it so we see every tenant's.
     */
    public function testimonials(Request $request)
    {
        $filter = in_array($request->query('show'), ['published', 'hidden'], true)
            ? $request->query('show')
            : null;
        $q = trim((string) $request->query('q', ''));

        $reviews = Review::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->guestTestimonials()
            ->with(['tenant:id,business_name,slug', 'subject:id,name', 'booking:id,check_out,guest_id', 'booking.guest:id,name'])
            ->when($filter === 'published', fn ($query) => $query->where('is_published', true))
            ->when($filter === 'hidden', fn ($query) => $query->where('is_published', false))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('comment', 'like', "%{$q}%")
                ->orWhere('guest_name', 'like', "%{$q}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $publishedTotal = Review::query()->withoutGlobalScope(BelongsToTenantScope::class)->guestTestimonials()->where('is_published', true)->count();
        $hiddenTotal = Review::query()->withoutGlobalScope(BelongsToTenantScope::class)->guestTestimonials()->where('is_published', false)->count();

        return view('platform.testimonials', [
            'reviews'        => $reviews,
            'publishedTotal' => $publishedTotal,
            'hiddenTotal'    => $hiddenTotal,
        ]);
    }

    /** Hide/show a testimonial (flip is_published). */
    public function toggleTestimonial(int $id)
    {
        $review = Review::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->findOrFail($id);

        $review->update(['is_published' => ! $review->is_published]);

        return back()->with('status', $review->is_published
            ? __('Testimonial is now visible on the homestay page.')
            : __('Testimonial hidden from the homestay page.'));
    }

    /** Permanently delete a testimonial (abuse / spam). */
    public function deleteTestimonial(int $id)
    {
        Review::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->findOrFail($id)
            ->delete();

        return back()->with('status', __('Testimonial deleted.'));
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
