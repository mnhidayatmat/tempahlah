<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use App\Models\Tenant;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Super-admin dashboard headline: how many tenants are on Free vs Paid, how
 * many are trialing / past due, and the resulting MRR. Reads straight off the
 * `subscriptions` table (one row per tenant via the hasOne relation).
 */
class SubscriptionStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Subscriptions';

    protected function getStats(): array
    {
        // One aggregate pass over subscriptions, grouped by plan + status.
        $rows = Subscription::query()
            ->selectRaw('plan, status, COUNT(*) as c, COALESCE(SUM(monthly_amount), 0) as amt')
            ->groupBy('plan', 'status')
            ->get();

        $count = fn (?string $plan = null, ?string $status = null) => (int) $rows
            ->when($plan !== null, fn ($r) => $r->where('plan', $plan))
            ->when($status !== null, fn ($r) => $r->where('status', $status))
            ->sum('c');

        $totalTenants = Tenant::query()->count();
        $withSub      = (int) $rows->sum('c');

        $free      = $count(Subscription::PLAN_FREE);
        $paidActive = $count(Subscription::PLAN_PAID, Subscription::STATUS_ACTIVE);
        $trialing  = $count(null, Subscription::STATUS_TRIALING);
        $pastDue   = $count(null, Subscription::STATUS_PAST_DUE);
        $cancelled = $count(null, Subscription::STATUS_CANCELLED);

        // MRR = actively-billing paid subs only (trials pay nothing yet).
        $mrr = (float) $rows
            ->where('plan', Subscription::PLAN_PAID)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->sum('amt');

        // Paying + trialing = the "subscribed" tenants the host cares about.
        $subscribed = $paidActive + $trialing;

        return [
            Stat::make('Total tenants', $totalTenants)
                ->description($withSub === $totalTenants
                    ? 'All have a subscription record'
                    : ($totalTenants - $withSub).' without a subscription record')
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->color('gray'),

            Stat::make('Free plan', $free)
                ->description($totalTenants > 0
                    ? round($free / max($totalTenants, 1) * 100).'% of tenants'
                    : 'No tenants yet')
                ->descriptionIcon(Heroicon::OutlinedGift)
                ->color('gray'),

            Stat::make('Subscribed (Paid)', $subscribed)
                ->description($trialing > 0
                    ? "{$paidActive} active · {$trialing} on trial"
                    : "{$paidActive} active")
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->color('success'),

            Stat::make('On trial', $trialing)
                ->description('7-day paid trial')
                ->descriptionIcon(Heroicon::OutlinedSparkles)
                ->color('info'),

            Stat::make('Past due', $pastDue)
                ->description($cancelled > 0 ? "{$cancelled} cancelled" : 'Payment overdue')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($pastDue > 0 ? 'warning' : 'gray'),

            Stat::make('MRR', 'RM '.number_format($mrr, 0))
                ->description("From {$paidActive} active paid")
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
        ];
    }
}
