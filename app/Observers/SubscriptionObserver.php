<?php

namespace App\Observers;

use App\Models\Subscription;
use Laravel\Pennant\Feature;

/**
 * Pennant runs on the `database` store, so every resolved flag is cached in the
 * `features` table keyed on the tenant scope. Nothing invalidates those rows on
 * its own — which meant an upgrade never unlocked anything and a downgrade never
 * locked anything back. Every paid flag resolves through Tenant::isPaid(), which
 * reads this model, so any write here can change every flag for the tenant.
 */
class SubscriptionObserver
{
    public function saved(Subscription $subscription): void
    {
        // On insert Eloquent never populates $changes, so wasChanged() is always
        // false for a freshly created row — check wasRecentlyCreated first.
        if (! $subscription->wasRecentlyCreated && ! $subscription->wasChanged($this->flagBearingColumns())) {
            return;
        }

        $this->purge($subscription);
    }

    public function deleted(Subscription $subscription): void
    {
        $this->purge($subscription);
    }

    private function purge(Subscription $subscription): void
    {
        // Only the plan-defining columns can flip a feature flag; a meta-only
        // touch must not churn the cache. Relation may be gone mid-cascade.
        $tenant = $subscription->tenant;

        if (! $tenant) {
            return;
        }

        // Drop this tenant's cached rows only — never other tenants'.
        Feature::for($tenant)->forget(Feature::defined());

        // Marketplace showcase band follows the tier (Ultra > Pro > Free) —
        // keep the tenant's listings' persisted rank current. Never let this
        // break the subscription save itself (e.g. mid-deploy before the
        // showcase_rank migration has run).
        try {
            \App\Models\MarketplaceListing::where('tenant_id', $tenant->id)->update([
                'showcase_rank' => \App\Support\Billing\Plans::rank($subscription->effectivePlanKey()),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('showcase_rank refresh failed', [
                'tenant_id' => $tenant->id, 'error' => $e->getMessage(),
            ]);
        }

        // Pennant also memoizes within the request; the observer can fire mid-request
        // (e.g. the upgrade POST) and the very next Feature::active() would otherwise
        // read the stale in-memory value.
        Feature::flushCache();
    }

    /**
     * @return array<int, string>
     */
    private function flagBearingColumns(): array
    {
        return ['plan', 'status', 'trial_ends_at', 'current_period_end', 'comped_at', 'grace_ends_at'];
    }
}
