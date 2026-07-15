<?php

namespace App\Livewire\Tenant;

use App\Models\Booking;
use App\Models\CleaningTask;
use App\Models\Expense;
use App\Models\LaundryTask;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Review;
use App\Services\Onboarding\SetupChecklist;
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

    /**
     * Mark the setup checklist's final step done: the host has shared their
     * booking link. Server-side gated on the core steps genuinely being green
     * (a crafted request can't skip setup). Re-rendering after this stamps the
     * checklist complete, so the "Get set up" card removes itself.
     */
    public function shareBookingLink(): void
    {
        $tenant = app(TenantContext::class)->current();
        if (! $tenant) {
            return;
        }

        $checklist = app(SetupChecklist::class)->for($tenant);
        $share = collect($checklist['steps'])->firstWhere('key', 'booking');

        // Only allow it once the link step is actually unlocked.
        if ($share && ($share['locked'] ?? false)) {
            return;
        }

        $tenant->markBookingLinkShared();
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
        // Per-homestay financial split (empty for a single-property tenant —
        // the grand-total stat cards already ARE that one homestay).
        $breakdown = $this->perHomestayBreakdown();

        $plan = $tenant?->subscription?->plan ?? 'free';

        // First-run setup checklist. Derived from live state, so it disappears
        // on its own once the tenant is genuinely ready to take bookings.
        $checklist = $tenant ? app(SetupChecklist::class)->for($tenant) : null;

        return view('livewire.tenant.dashboard', [
            'tenant' => $tenant,
            'publicUrl' => $tenant?->publicUrl(),
            'stats' => $stats,
            'series' => $series,
            'transactions' => $transactions,
            'shelf' => $shelf,
            'actions' => $actions,
            'breakdown' => $breakdown,
            'plan' => $plan,
            'isPro' => $plan !== 'free',
            'checklist' => $checklist,
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

        [$expectedAmount, $expectedCount] = $this->expectedPayments();

        // Cumulative all-time earnings (reuse revenue() semantics with a far-past
        // start) + this calendar month's earnings + this month's operating cost.
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $cumulative = (float) $svc->revenue(Carbon::create(2000, 1, 1)->startOfDay(), $now->copy()->endOfDay());
        $monthRevenue = (float) $svc->revenue($monthStart, $monthEnd);
        $monthCost = $this->monthlyOperatingCost($monthStart, $monthEnd);

        return [
            'revenue' => $revenue,
            'bookings' => $activeBookings,
            'properties' => $properties,
            'rooms' => $rooms,
            'rating' => round($reviewAvg, 1),
            'reviews' => $reviewCount,
            'expected' => $expectedAmount,
            'expected_count' => $expectedCount,
            'cumulative' => $cumulative,
            'month_revenue' => $monthRevenue,
            'month_cost' => $monthCost,
        ];
    }

    /**
     * This calendar month's operating cost: cleaning + laundry + maintenance
     * task costs + standalone expenses whose activity date falls in the month.
     * Tenant-scoped via the BelongsToTenant global scope on each model.
     * Cleaning is dated by its scheduled date, laundry by pickup, maintenance
     * by when it was resolved (the point the host records the repair cost), and
     * expenses by the host-entered incurred date.
     */
    protected function monthlyOperatingCost(Carbon $monthStart, Carbon $monthEnd, ?int $propertyId = null): float
    {
        $forProperty = fn ($q) => $q->when($propertyId, fn ($qq) => $qq->where('property_id', $propertyId));

        $cleaning = (float) CleaningTask::query()->tap($forProperty)
            ->whereBetween('scheduled_at', [$monthStart, $monthEnd])
            ->sum('cost');

        $laundry = (float) LaundryTask::query()->tap($forProperty)
            ->whereBetween('pickup_at', [$monthStart, $monthEnd])
            ->sum('cost');

        $maintenance = (float) MaintenanceTicket::query()->tap($forProperty)
            ->whereBetween('resolved_at', [$monthStart, $monthEnd])
            ->sum('cost');

        // Expenses may be unassigned (nullable property_id) — a per-property
        // total excludes those, so the sum of homestay costs can be less than
        // the tenant-wide total. The dashboard surfaces that gap as a note.
        $expenses = (float) Expense::query()->tap($forProperty)
            ->whereBetween('incurred_at', [$monthStart, $monthEnd])
            ->sum('amount');

        return round($cleaning + $laundry + $maintenance + $expenses, 2);
    }

    /**
     * Per-homestay financial split: each of the tenant's properties with its
     * all-time earnings, this-month earnings, expected (outstanding) payments,
     * and this-month operating cost. Returns [] for a single-property tenant —
     * the grand-total stat cards already represent that one homestay, so the
     * breakdown table is redundant and the view skips it.
     *
     * Reuses StatisticsService::revenue()'s per-property filter (same booking
     * set + check-in dating as the grand-total cards), so summing the earnings
     * / this-month / expected columns reconciles exactly to the totals. Cost is
     * the one column that can under-sum, by design (see monthlyOperatingCost).
     *
     * @return list<array{id:int, name:string, earnings:float, month:float, expected:float, expected_count:int, cost:float}>
     */
    protected function perHomestayBreakdown(): array
    {
        $properties = Property::query()->orderBy('name')->get(['id', 'name']);
        if ($properties->count() <= 1) {
            return [];
        }

        $svc = app(StatisticsService::class);
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $farPast = Carbon::create(2000, 1, 1)->startOfDay();
        $nowEnd = $now->copy()->endOfDay();

        return $properties->map(function (Property $p) use ($svc, $farPast, $nowEnd, $monthStart, $monthEnd) {
            [$expAmount, $expCount] = $this->expectedPayments($p->id);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'earnings' => (float) $svc->revenue($farPast, $nowEnd, $p->id),
                'month' => (float) $svc->revenue($monthStart, $monthEnd, $p->id),
                'expected' => $expAmount,
                'expected_count' => $expCount,
                'cost' => $this->monthlyOperatingCost($monthStart, $monthEnd, $p->id),
            ];
        })->all();
    }

    /**
     * Future ("expected") payments — the host's accounts receivable. A booking
     * counts here when its merged payment status is exactly "Paid Booking Fee":
     * the guest has paid the booking fee but NOT the full payment. The filter
     * below mirrors Booking::paymentStatusKey() === 'paid_booking_fee' (a
     * `confirmed` booking, or a `pending` one with the deposit stamped, that
     * isn't yet fully paid / checked-in / checked-out / cancelled), so this
     * card's booking count always reconciles with the bookings list.
     *
     * The outstanding balance per booking is `total_amount − amountPaid`, where
     * amountPaid is the GREATER of recorded succeeded payments and the booking
     * fee snapshot. We deliberately do NOT use `deposit_amount` as the paid
     * portion: the Wafa register import set `deposit_amount = total_amount` on
     * historical rows, which would zero-out their (genuinely still-owed)
     * balances. `booking_fee_amount` is the reliable "already collected" figure
     * for a Paid-Booking-Fee booking. Tenant-scoped via the global scope.
     *
     * @return array{0: float, 1: int} [total outstanding, booking count]
     */
    protected function expectedPayments(?int $propertyId = null): array
    {
        $rows = Booking::query()
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->whereNull('balance_paid_at')
            ->where(function ($q) {
                $q->where('status', Booking::STATUS_CONFIRMED)
                    ->orWhere(function ($q2) {
                        $q2->where('status', Booking::STATUS_PENDING)
                            ->whereNotNull('deposit_paid_at');
                    });
            })
            ->withSum(['payments as paid_sum' => function ($q) {
                $q->where('status', 'succeeded');
            }], 'amount')
            ->get(['id', 'total_amount', 'booking_fee_amount']);

        $amount = 0.0;
        $count = 0;
        foreach ($rows as $row) {
            $paid = max((float) $row->paid_sum, (float) $row->booking_fee_amount);
            $outstanding = (float) $row->total_amount - $paid;
            if ($outstanding > 0.0) {
                $amount += $outstanding;
                $count++;
            }
        }

        return [round($amount, 2), $count];
    }

    /**
     * Cumulative income over the range as ONE SERIES PER HOMESTAY, so a
     * multi-property host sees each homestay's velocity as its own line in a
     * single graph. A single-property tenant gets one line (visually the same
     * as before). Payments carry no property_id, so each is attributed to a
     * property via its booking.
     */
    protected function revenueSeries(Carbon $start, Carbon $end): array
    {
        // 11 evenly-spaced, contiguous, non-overlapping buckets across the range.
        $points = 11;
        $span = max(1, $start->diffInDays($end));
        $step = (int) ceil($span / ($points - 1));

        $labels = [];
        $buckets = [];
        $cursor = $start->copy();
        for ($i = 0; $i < $points; $i++) {
            $bucketEnd = $cursor->copy()->addDays($step)->min($end);
            $buckets[] = [$cursor->copy(), $bucketEnd->copy()];
            $labels[] = $cursor->format('M d');
            $cursor = $bucketEnd->copy()->addSecond();
            if ($cursor->greaterThanOrEqualTo($end)) {
                break;
            }
        }
        // At least 2 points so the chart can draw a line.
        while (count($labels) < 2) {
            $labels[] = $end->format('M d');
            $buckets[] = [$end->copy(), $end->copy()];
        }

        $properties = Property::query()->orderBy('name')->get(['id', 'name']);

        // All succeeded payments in range with their booking's property_id.
        $payments = Payment::query()
            ->where('status', 'succeeded')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->with('booking:id,property_id')
            ->get(['id', 'booking_id', 'amount', 'paid_at']);

        // Sum each payment into [property_id][bucketIndex].
        $matrix = [];
        foreach ($payments as $pay) {
            $pid = $pay->booking?->property_id;
            if ($pid === null || $pay->paid_at === null) {
                continue;
            }
            foreach ($buckets as $bi => [$bStart, $bEnd]) {
                if ($pay->paid_at->betweenIncluded($bStart, $bEnd)) {
                    $matrix[$pid][$bi] = ($matrix[$pid][$bi] ?? 0.0) + (float) $pay->amount;
                    break;
                }
            }
        }

        // Per-homestay line colours. The first four lean on Tempahlah theme
        // tokens so the chart stays on-brand and reads consistently with the rest
        // of the app (matches each tenant's palette): line 1 brand blue, line 2
        // brand yellow (var(--accent) — the same gold the Reports chart uses),
        // line 3 brand teal, line 4 green. Distinct hues follow for the rare 5th+
        // homestay. Ordered blue→yellow→teal→green for max adjacent contrast (the
        // old #2563eb blue clashed with the brand-blue line 1).
        $palette = ['var(--primary)', 'var(--accent)', 'var(--secondary)', 'var(--ok)', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b'];

        $seriesList = [];
        $globalMax = 1;
        foreach ($properties->values() as $idx => $p) {
            $running = 0.0;
            $values = [];
            foreach ($buckets as $bi => $b) {
                $running += (float) ($matrix[$p->id][$bi] ?? 0.0);
                $values[] = (int) round($running);
            }
            $globalMax = max($globalMax, max($values ?: [0]));
            $seriesList[] = [
                'id' => $p->id,
                'name' => $p->name,
                'color' => $palette[$idx % count($palette)],
                'values' => $values,
            ];
        }

        return [
            'labels' => $labels,
            'series' => $seriesList,
            'max' => $globalMax,
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
        // No cap — a multi-property host should see every homestay here.
        return Property::query()
            ->withCount('rooms')
            ->orderByDesc('id')
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
                'route' => route('tenant.bookings.index', ['status' => 'deposit-due']),
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
