<?php

namespace App\Jobs;

use App\Models\Commission;
use App\Models\Payout;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RunMonthlyCommissionPayout implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $periodStart = null,
        public ?string $periodEnd = null,
    ) {}

    public function handle(): void
    {
        $start = $this->periodStart
            ? \Carbon\CarbonImmutable::parse($this->periodStart)
            : now()->subMonth()->startOfMonth();
        $end = $this->periodEnd
            ? \Carbon\CarbonImmutable::parse($this->periodEnd)
            : now()->subMonth()->endOfMonth();

        $tenants = Tenant::whereHas('subscription', fn ($q) => $q->where('plan', 'paid'))->get();

        foreach ($tenants as $tenant) {
            DB::transaction(function () use ($tenant, $start, $end) {
                $commissions = Commission::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', Commission::STATUS_PENDING)
                    ->whereHas('booking', fn ($q) => $q->whereBetween('check_out', [$start, $end]))
                    ->get();

                if ($commissions->isEmpty()) {
                    return;
                }

                $payout = Payout::create([
                    'tenant_id' => $tenant->id,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'booking_count' => $commissions->count(),
                    'gross_total' => $commissions->sum('gross_amount'),
                    'commission_total' => $commissions->sum('commission_amount'),
                    'gateway_fees_total' => $commissions->sum('gateway_fee'),
                    'net_amount' => $commissions->sum('payout_amount'),
                    'currency' => 'MYR',
                    'status' => Payout::STATUS_PENDING,
                ]);

                Commission::whereIn('id', $commissions->pluck('id'))
                    ->update([
                        'status' => Commission::STATUS_SETTLED,
                        'payout_id' => $payout->id,
                        'settled_at' => now(),
                    ]);
            });
        }
    }
}
