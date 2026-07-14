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
        $subscription = $tenant->subscription;

        // Pre-fill the grant duration control so re-saving the form doesn't
        // silently convert a time-limited grant into a permanent comp. A comp
        // reads as "unlimited"; a live non-comped trial reads as its remaining
        // days (so a no-op save keeps roughly the same expiry date).
        $grantDuration = 'unlimited';
        $grantLength = 30;
        if ($subscription && ! $subscription->isComped() && $subscription->onTrial()) {
            $grantDuration = 'days';
            $grantLength = max(1, (int) ceil(now()->diffInDays($subscription->trial_ends_at, false)));
        }

        return view('platform.tenant-edit', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            // Reflect actual access (comped / active / trialing / in-grace):
            // the effective tier — free, pro or ultra.
            'currentPlan' => $tenant->planKey(),
            'grantDuration' => $grantDuration,
            'grantLength' => $grantLength,
        ]);
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'business_name'  => ['required', 'string', 'max:160'],
            'business_email' => ['nullable', 'email', 'max:190'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'status'         => ['required', 'in:active,suspended'],
            'plan'           => ['required', 'in:free,pro,ultra'],
            // How long a Pro/Ultra grant lasts. Ignored for the free plan.
            'grant_duration' => ['required', 'in:unlimited,days,months'],
            'grant_length'   => ['nullable', 'integer', 'min:1', 'max:3650', 'required_unless:grant_duration,unlimited'],
        ], [
            'grant_length.required_unless' => __('Enter how many days or months the grant lasts.'),
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

        $this->applyPlan(
            $tenant,
            $validated['plan'],
            $validated['grant_duration'],
            isset($validated['grant_length']) ? (int) $validated['grant_length'] : null,
        );

        return redirect()->route('platform.overview')
            ->with('status', __('Tenant ":name" updated.', ['name' => $tenant->business_name]));
    }

    /**
     * Delete a tenant. Soft delete (Tenant uses SoftDeletes) — it drops off the
     * platform list and its public booking page stops resolving, but the data
     * is retained and can be restored, so an accidental click isn't catastrophic.
     * Refuses to delete the tenant the admin is currently signed in as (that
     * would break their own session).
     */
    public function destroyTenant(Request $request, Tenant $tenant)
    {
        $current = app(\App\Support\Tenancy\TenantContext::class)->current();
        if ($current && $current->id === $tenant->id) {
            return back()->with('error', __('You can\'t delete the homestay you\'re currently signed in as.'));
        }

        $name = $tenant->business_name;
        $tenant->delete();

        // Release the slug so the same homestay name can be registered again.
        // The tenants.slug unique index still counts soft-deleted rows, so a
        // deleted tenant holding "demo-homestay" would otherwise block a new
        // "demo-homestay" INSERT (duplicate-key 500). Suffix with the id to stay
        // unique; the row is still recoverable, just under a parked slug.
        $tenant->forceFill(['slug' => $tenant->slug.'-del-'.$tenant->id])->saveQuietly();

        return redirect()->route('platform.overview')
            ->with('status', __('Tenant ":name" deleted.', ['name' => $name]));
    }

    /**
     * Force a tenant onto free, Pro or Ultra. A paid grant here is COMPLIMENTARY
     * (never billed, excluded from MRR) — the designed way to hold every paid
     * feature without running the billing flow (partner, offline payment, staff).
     * Writing through the model fires SubscriptionObserver, which purges that
     * tenant's cached Pennant flags so features flip immediately.
     *
     * A paid grant lasts either:
     *   - 'unlimited'  → comped_at set. Never expires, never downgraded (the
     *                    lifecycle command skips comped rows).
     *   - 'days'/'months' → status=trialing + trial_ends_at set (comped_at null).
     *                    ProcessSubscriptionLifecycle auto-downgrades it to free
     *                    once trial_ends_at lapses, and effectivePlanKey() already
     *                    honours trial_ends_at for a trialing sub — so the tenant
     *                    holds the tier for exactly the granted window.
     */
    private function applyPlan(Tenant $tenant, string $plan, string $duration = 'unlimited', ?int $length = null): void
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

        if (in_array($plan, Subscription::PAID_PLANS, true)) {
            if ($duration === 'unlimited') {
                $subscription->update([
                    // The comp holds the tier on the plan column — a Pro comp and
                    // an Ultra comp grant different feature sets.
                    'plan' => $plan,
                    'status' => Subscription::STATUS_ACTIVE,
                    // Keep an existing comp date; otherwise stamp now.
                    'comped_at' => $subscription->comped_at ?? now(),
                    // 0 so this complimentary grant never inflates paying MRR.
                    'monthly_amount' => 0,
                    'billing_method' => 'manual',
                    'grace_ends_at' => null,
                    'trial_ends_at' => null,
                    'cancelled_at' => null,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYear(),
                ]);
            } else {
                // Time-limited complimentary grant. Modelled as a trial so the
                // existing lifecycle command expires it cleanly with no billing.
                $endsAt = $duration === 'months'
                    ? now()->addMonthsNoOverflow(max(1, (int) $length))
                    : now()->addDays(max(1, (int) $length));

                $subscription->update([
                    'plan' => $plan,
                    'status' => Subscription::STATUS_TRIALING,
                    // Not a comp — must be able to lapse to free at the end.
                    'comped_at' => null,
                    'trial_ends_at' => $endsAt,
                    // Mark the trial as used so it isn't confused with (or double
                    // counted against) the self-serve 7-day free trial.
                    'trial_used_at' => $subscription->trial_used_at ?? now(),
                    'monthly_amount' => 0,
                    'billing_method' => 'manual',
                    'grace_ends_at' => null,
                    'cancelled_at' => null,
                    'current_period_start' => now(),
                    'current_period_end' => $endsAt,
                ]);
            }
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
                'price_id_ultra' => (string) PlatformSetting::get('stripe.price_id_ultra', ''),
            ],
            'stripeEnabled' => $stripe->enabled(),
            'webhookUrl' => url('/api/webhooks/stripe'),
            // Meta Pixel ID is a public value (ships in page HTML) — show in full.
            // UI value wins, else the FACEBOOK_PIXEL_ID env fallback (matches the partial).
            'facebookPixelId' => (string) PlatformSetting::get('facebook_pixel.id', ''),
            'facebookPixelActive' => filled(PlatformSetting::get('facebook_pixel.id') ?: config('services.facebook_pixel.id')),
        ]);
    }

    /**
     * Marketing tags (Meta Pixel). Separate form/handler from Stripe so saving
     * one card never clears the other's fields. The Pixel ID is a public value,
     * so no masking — always set (blank clears it → falls back to .env, or off).
     */
    public function updateMarketing(Request $request)
    {
        $validated = $request->validate([
            'facebook_pixel_id' => ['nullable', 'string', 'max:40', 'regex:/^[0-9]*$/'],
        ], [
            'facebook_pixel_id.regex' => __('The Meta Pixel ID is digits only (from Events Manager).'),
        ]);

        PlatformSetting::put('facebook_pixel.id', trim((string) ($validated['facebook_pixel_id'] ?? '')));

        return redirect()->route('platform.settings')->with('status', __('Marketing settings saved.'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'stripe_secret_key' => ['nullable', 'string', 'max:255'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'stripe_price_id' => ['nullable', 'string', 'max:120'],
            'stripe_price_id_ultra' => ['nullable', 'string', 'max:120'],
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
        PlatformSetting::put('stripe.price_id_ultra', trim((string) ($validated['stripe_price_id_ultra'] ?? '')));

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
        $filter = in_array($request->query('show'), ['published', 'hidden', 'appealed'], true)
            ? $request->query('show')
            : null;
        $q = trim((string) $request->query('q', ''));

        $reviews = Review::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->guestTestimonials()
            ->with(['tenant:id,business_name,slug', 'subject:id,name', 'booking:id,check_out,guest_id', 'booking.guest:id,name'])
            ->when($filter === 'published', fn ($query) => $query->where('is_published', true))
            ->when($filter === 'hidden', fn ($query) => $query->where('is_published', false))
            ->when($filter === 'appealed', fn ($query) => $query->where('appeal_status', Review::APPEAL_PENDING))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('comment', 'like', "%{$q}%")
                ->orWhere('guest_name', 'like', "%{$q}%")))
            // Pending appeals surface first so the admin sees what needs a decision.
            ->orderByRaw("CASE WHEN appeal_status = '".Review::APPEAL_PENDING."' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $base = fn () => Review::query()->withoutGlobalScope(BelongsToTenantScope::class)->guestTestimonials();
        $publishedTotal = $base()->where('is_published', true)->count();
        $hiddenTotal = $base()->where('is_published', false)->count();
        $appealedTotal = $base()->where('appeal_status', Review::APPEAL_PENDING)->count();

        return view('platform.testimonials', [
            'reviews'        => $reviews,
            'publishedTotal' => $publishedTotal,
            'hiddenTotal'    => $hiddenTotal,
            'appealedTotal'  => $appealedTotal,
        ]);
    }

    /** Resolve a tenant's appeal to hide a testimonial: approve (hide) or reject. */
    public function resolveAppeal(Request $request, int $id)
    {
        $review = Review::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->findOrFail($id);

        $data = $request->validate([
            'decision'   => 'required|in:approve,reject',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $note = trim((string) ($data['admin_note'] ?? '')) ?: null;

        if ($data['decision'] === 'approve') {
            $review->update([
                'is_published'       => false,
                'appeal_status'      => Review::APPEAL_APPROVED,
                'appeal_reviewed_at' => now(),
                'appeal_admin_note'  => $note,
            ]);

            return back()->with('status', __('Appeal approved — testimonial is now hidden from the homestay page.'));
        }

        $review->update([
            'appeal_status'      => Review::APPEAL_REJECTED,
            'appeal_reviewed_at' => now(),
            'appeal_admin_note'  => $note,
        ]);

        return back()->with('status', __('Appeal declined — testimonial stays visible.'));
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
        // Only count subscriptions of LIVE tenants. Subscription carries no
        // soft-delete/tenant scope, and deleting a tenant only soft-deletes the
        // tenant (its subscription row stays) — so without this, Free / Subscribed
        // / On-trial / MRR kept counting deleted tenants while Total tenants (a
        // soft-delete-scoped Tenant::count() below) already excluded them.
        // whereHas('tenant') applies Tenant's SoftDeletes global scope and drops
        // orphaned rows whose tenant was hard-deleted.
        $rows = Subscription::query()
            ->whereHas('tenant')
            ->selectRaw('plan, status, COUNT(*) as c, COALESCE(SUM(monthly_amount), 0) as amt')
            ->groupBy('plan', 'status')
            ->get();

        $count = fn (?string $plan = null, ?string $status = null) => (int) $rows
            ->when($plan !== null, fn ($r) => $r->where('plan', $plan))
            ->when($status !== null, fn ($r) => $r->where('status', $status))
            ->sum('c');

        $totalTenants = Tenant::query()->count();
        $free       = $count(Subscription::PLAN_FREE);
        $paidActive = $count(Subscription::PLAN_PRO, Subscription::STATUS_ACTIVE)
            + $count(Subscription::PLAN_ULTRA, Subscription::STATUS_ACTIVE);
        $trialing   = $count(null, Subscription::STATUS_TRIALING);
        $pastDue    = $count(null, Subscription::STATUS_PAST_DUE);
        $cancelled  = $count(null, Subscription::STATUS_CANCELLED);

        $mrr = (float) $rows
            ->whereIn('plan', Subscription::PAID_PLANS)
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
        $plan = in_array($request->query('plan'), [Subscription::PLAN_FREE, Subscription::PLAN_PRO, Subscription::PLAN_ULTRA], true)
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
