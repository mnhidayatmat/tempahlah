<?php

namespace App\Livewire\Tenant;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Review;
use App\Services\Reports\StatisticsService;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public string $range = '30d';

    public function setRange(string $range): void
    {
        $this->range = in_array($range, ['30d', 'qtr', 'ytd'], true) ? $range : '30d';
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->current();

        [$start, $end] = $this->rangeBounds();

        $stats = $this->computeStats($start, $end);
        $series = $this->revenueSeries($start, $end);
        $transactions = $this->recentTransactions();
        $shelf = $this->propertyShelf();
        $actions = $this->actionQueueFor($tenant);

        $plan = $tenant?->subscription?->plan ?? 'free';

        return view('livewire.tenant.dashboard', [
            'tenant' => $tenant,
            'stats' => $stats,
            'series' => $series,
            'transactions' => $transactions,
            'shelf' => $shelf,
            'actions' => $actions,
            'plan' => $plan,
            'isPro' => $plan !== 'free',
        ])->layout('layouts.app', [
            'title' => __('Dashboard'),
            'breadcrumbs' => [$tenant?->business_name ?? __('Workspace')],
        ]);
    }

    protected function rangeBounds(): array
    {
        return match ($this->range) {
            'qtr' => [Carbon::now()->subDays(90)->startOfDay(), Carbon::now()->endOfDay()],
            'ytd' => [Carbon::now()->startOfYear(), Carbon::now()->endOfDay()],
            default => [Carbon::now()->subDays(30)->startOfDay(), Carbon::now()->endOfDay()],
        };
    }

    protected function computeStats(Carbon $start, Carbon $end): array
    {
        $svc = app(StatisticsService::class);

        $revenue = (float) $svc->revenue($start, $end);

        $activeBookings = Booking::query()
            ->whereIn('status', [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_PENDING,
            ])
            ->whereBetween('check_in', [$start, $end])
            ->count();

        $properties = Property::query()->count();
        $rooms = DB::table('rooms')->count();

        // Average review rating, falling back to property aggregate if reviews are empty
        $reviewAvg = (float) Review::query()->avg('rating_overall');
        if ($reviewAvg <= 0) {
            $reviewAvg = (float) Property::query()->avg('star_rating') ?: 4.8;
        }
        $reviewCount = (int) Review::query()->count();

        return [
            'revenue' => $revenue,
            'bookings' => $activeBookings,
            'properties' => $properties,
            'rooms' => $rooms,
            'rating' => round($reviewAvg, 1),
            'reviews' => $reviewCount,
        ];
    }

    protected function revenueSeries(Carbon $start, Carbon $end): array
    {
        // 11 evenly-spaced sample points across the range
        $points = 11;
        $span = max(1, $start->diffInDays($end));
        $step = (int) ceil($span / ($points - 1));

        $labels = [];
        $values = [];

        $cursor = $start->copy();
        $running = 0.0;
        for ($i = 0; $i < $points; $i++) {
            $bucketEnd = $cursor->copy()->addDays($step)->min($end);
            $bucketRevenue = (float) Payment::query()
                ->where('status', 'succeeded')
                ->whereBetween('paid_at', [$cursor, $bucketEnd])
                ->sum('amount');
            $running += $bucketRevenue;
            $labels[] = $cursor->format('M d');
            $values[] = (int) round($running);
            $cursor = $bucketEnd->copy()->addSecond();
            if ($cursor->greaterThanOrEqualTo($end)) break;
        }

        // Pad to at least 2 points so the chart can draw
        while (count($values) < 2) {
            $values[] = end($values) ?: 0;
            $labels[] = $end->format('M d');
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'max' => max(1, max($values)),
        ];
    }

    protected function recentTransactions()
    {
        return Payment::query()
            ->with(['booking.guest:id,name', 'booking.property:id,name'])
            ->where('status', 'succeeded')
            ->latest('paid_at')
            ->limit(4)
            ->get()
            ->map(function (Payment $p) {
                $b = $p->booking;
                $guest = $b?->guest?->name ?? __('Guest');
                $propertyName = $b?->property?->name ?? '—';
                $payout = (float) $p->amount * 0.97; // 3% marketplace fee max
                return [
                    'guest' => $guest,
                    'property' => $propertyName,
                    'when' => optional($p->paid_at)->diffForHumans() ?? '—',
                    'amount' => (float) $p->amount,
                    'payout' => round($payout, 2),
                ];
            });
    }

    protected function propertyShelf()
    {
        return Property::query()
            ->withCount('rooms')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(function (Property $p) {
                $revenue = (float) Booking::query()
                    ->where('property_id', $p->id)
                    ->whereIn('status', [Booking::STATUS_CHECKED_OUT, Booking::STATUS_CHECKED_IN, Booking::STATUS_CONFIRMED])
                    ->where('created_at', '>=', now()->subDays(30))
                    ->sum('total_amount');

                $startingRate = (float) DB::table('rooms')
                    ->where('property_id', $p->id)
                    ->min('base_price') ?: 0;

                $p->stats_revenue_30d = $revenue;
                $p->stats_starting_rate = $startingRate;
                return $p;
            });
    }

    protected function actionQueueFor($tenant): array
    {
        $items = [];

        $depositsDue = Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING])
            ->whereNull('deposit_paid_at')
            ->whereBetween('check_in', [now(), now()->addDays(7)])
            ->count();
        if ($depositsDue) {
            $items[] = [
                'icon' => 'alert',
                'tone' => 'warn',
                'title' => trans_choice(':count deposit due in 7 days|:count deposits due in 7 days', $depositsDue),
                'cta' => __('View bookings'),
                'route' => route('tenant.bookings.index'),
            ];
        }

        $newRequests = Booking::query()
            ->where('status', Booking::STATUS_PENDING)
            ->count();
        if ($newRequests) {
            $items[] = [
                'icon' => 'clock',
                'tone' => 'info',
                'title' => trans_choice(':count new booking request|:count new booking requests', $newRequests),
                'cta' => __('Review'),
                'route' => route('tenant.bookings.index'),
            ];
        }

        if (empty($items)) {
            $items[] = [
                'icon' => 'check',
                'tone' => 'ok',
                'title' => __('All clear — no urgent actions.'),
                'cta' => null,
                'route' => null,
            ];
        }

        return $items;
    }
}
