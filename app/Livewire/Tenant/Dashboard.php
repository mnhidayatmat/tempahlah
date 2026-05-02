<?php

namespace App\Livewire\Tenant;

use App\Models\Booking;
use App\Models\Property;
use App\Services\Reports\StatisticsService;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $tenant = app(TenantContext::class)->current();

        $stats = $this->computeStats($tenant);
        $upcoming = $this->upcomingBookings();
        $properties = $this->propertiesWithTonightStatus();
        $actions = $this->actionQueueFor($tenant);
        $channelMix = $this->channelMixFor($tenant);

        $plan = $tenant?->subscription?->plan ?? 'free';
        $isPro = $plan !== 'free';

        return view('livewire.tenant.dashboard', [
            'tenant' => $tenant,
            'stats' => $stats,
            'upcoming' => $upcoming,
            'properties' => $properties,
            'actions' => $actions,
            'channelMix' => $channelMix,
            'plan' => $plan,
            'isPro' => $isPro,
            'greeting' => $this->greeting(),
        ])->layout('layouts.app', ['title' => __('Dashboard')]);
    }

    protected function computeStats($tenant): array
    {
        $svc = app(StatisticsService::class);
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $priorStart = $start->copy()->subMonth()->startOfMonth();
        $priorEnd = $start->copy()->subDay()->endOfDay();

        $revenue = $svc->revenue($start, $end);
        $priorRevenue = $svc->revenue($priorStart, $priorEnd);
        $occupancy = $svc->occupancy($start, $end);
        $priorOccupancy = $svc->occupancy($priorStart, $priorEnd);
        $adr = $svc->adr($start, $end);
        $priorAdr = $svc->adr($priorStart, $priorEnd);

        $bookings = Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->whereBetween('check_in', [$start, $end])
            ->count();
        $priorBookings = Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->whereBetween('check_in', [$priorStart, $priorEnd])
            ->count();

        $spark = fn () => collect(range(1, 12))->map(fn () => rand(40, 100))->toArray();

        return [
            'revenue' => $revenue,
            'revenue_delta' => $this->formatDelta($revenue, $priorRevenue),
            'revenue_spark' => $spark(),
            'bookings' => $bookings,
            'bookings_delta' => $this->formatDelta($bookings, $priorBookings),
            'bookings_spark' => $spark(),
            'occupancy' => round($occupancy),
            'occupancy_delta' => $this->formatDelta($occupancy, $priorOccupancy, true),
            'occupancy_spark' => $spark(),
            'adr' => round($adr),
            'adr_delta' => $this->formatDelta($adr, $priorAdr),
            'adr_spark' => $spark(),
        ];
    }

    protected function formatDelta(float $current, float $prior, bool $absolute = false): string
    {
        if ($prior <= 0) return '—';
        $diff = $absolute ? ($current - $prior) : (($current - $prior) / $prior) * 100;
        $sign = $diff >= 0 ? '+' : '';
        return $sign.round($diff).'%';
    }

    protected function upcomingBookings()
    {
        return Booking::query()
            ->with(['guest:id,name', 'property:id,name'])
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING, Booking::STATUS_CHECKED_IN])
            ->whereBetween('check_in', [now()->startOfDay(), now()->addDays(14)->endOfDay()])
            ->orderBy('check_in')
            ->limit(6)
            ->get()
            ->map(function ($b) {
                $b->guest_display_name = $b->guest?->name ?? __('Guest');
                $b->payment_state = $this->paymentState($b);
                return $b;
            });
    }

    protected function paymentState($booking): string
    {
        if ($booking->balance_paid_at) return 'paid';
        if ($booking->deposit_paid_at) return 'deposit';
        return 'unpaid';
    }

    protected function propertiesWithTonightStatus()
    {
        return Property::query()
            ->limit(6)
            ->get()
            ->map(function ($p) {
                $p->tonight_status = $this->tonightStatusFor($p);
                return $p;
            });
    }

    protected function tonightStatusFor($property): array
    {
        $today = now()->toDateString();
        $current = Booking::query()
            ->with('guest:id,name')
            ->where('property_id', $property->id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
            ->where('check_in', '<=', $today)
            ->where('check_out', '>', $today)
            ->first();

        if ($current) {
            return [
                'state' => 'occupied',
                'label' => __('Occupied'),
                'guest' => $current->guest?->name,
            ];
        }

        $checkIn = Booking::query()
            ->with('guest:id,name')
            ->where('property_id', $property->id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING])
            ->whereDate('check_in', $today)
            ->first();

        if ($checkIn) {
            return [
                'state' => 'checkin',
                'label' => __('Check-in today'),
                'guest' => $checkIn->guest?->name,
            ];
        }

        return ['state' => 'vacant', 'label' => __('Vacant')];
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

        $openMaintenance = \App\Models\MaintenanceTicket::where('status', 'open')
            ->where('priority', 'high')
            ->count();
        if ($openMaintenance) {
            $items[] = [
                'icon' => 'alert',
                'tone' => 'err',
                'title' => trans_choice(':count high-priority maintenance ticket|:count high-priority maintenance tickets', $openMaintenance),
                'cta' => __('Triage'),
                'route' => route('tenant.housekeeping.index', ['tab' => 'maintenance']),
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

    protected function channelMixFor($tenant): array
    {
        $rows = Booking::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->select('channel', DB::raw('count(*) as c'))
            ->groupBy('channel')
            ->get();

        $total = max(1, (int) $rows->sum('c'));

        return $rows->map(fn ($r) => [
            'source' => $r->channel ?? 'unknown',
            'label' => ucfirst((string) ($r->channel ?? 'Unknown')),
            'count' => (int) $r->c,
            'pct' => (int) round(($r->c / $total) * 100),
        ])->values()->toArray();
    }

    protected function greeting(): string
    {
        $h = (int) now()->format('G');
        if ($h < 12) return __('Good morning');
        if ($h < 18) return __('Good afternoon');
        return __('Good evening');
    }
}
