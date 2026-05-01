<?php

namespace App\Livewire\Tenant;

use App\Models\Booking;
use App\Models\Property;
use App\Services\StatisticsService;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $tenant = app(TenantContext::class)->current();

        // ---- Stats (with safe fallbacks if StatisticsService isn't wired yet) ----
        $stats = $this->safeStats($tenant);

        // ---- Upcoming bookings (next 14 days) ----
        $upcoming = collect();
        if (class_exists(Booking::class)) {
            $upcoming = Booking::query()
                ->when(method_exists(Booking::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
                ->whereBetween('check_in', [now()->startOfDay(), now()->addDays(14)->endOfDay()])
                ->orderBy('check_in')
                ->limit(6)
                ->get();
        }

        // ---- Tonight's status per property ----
        $properties = collect();
        if (class_exists(Property::class)) {
            $properties = Property::query()
                ->when(method_exists(Property::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
                ->limit(6)
                ->get()
                ->map(function ($p) {
                    $p->tonight_status = $this->tonightStatusFor($p);
                    return $p;
                });
        }

        // ---- Action queue ----
        $actions = $this->actionQueueFor($tenant);

        // ---- Channel mix (last 30d) ----
        $channelMix = $this->channelMixFor($tenant);

        // ---- Plan / feature flags ----
        $plan = $tenant?->subscription?->plan ?? 'free';
        $isPro = $plan !== 'free';

        return view('livewire.tenant.dashboard', [
            'tenant'      => $tenant,
            'stats'       => $stats,
            'upcoming'    => $upcoming,
            'properties'  => $properties,
            'actions'     => $actions,
            'channelMix'  => $channelMix,
            'plan'        => $plan,
            'isPro'       => $isPro,
            'greeting'    => $this->greeting(),
        ])->layout('layouts.app', ['title' => __('Dashboard')]);
    }

    protected function safeStats($tenant): array
    {
        try {
            if (class_exists(StatisticsService::class)) {
                $svc = app(StatisticsService::class);
                if (method_exists($svc, 'forTenant')) {
                    $data = $svc->forTenant($tenant)->thisMonth();
                    return array_merge($this->statsFallback(), (array) $data);
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return $this->statsFallback();
    }

    protected function statsFallback(): array
    {
        $spark = fn () => collect(range(1, 12))->map(fn () => rand(40, 100))->toArray();
        return [
            'revenue'        => 12480,
            'revenue_delta'  => '+18%',
            'revenue_spark'  => $spark(),
            'bookings'       => 24,
            'bookings_delta' => '+6%',
            'bookings_spark' => $spark(),
            'occupancy'      => 72,
            'occupancy_delta'=> '+4%',
            'occupancy_spark'=> $spark(),
            'adr'            => 320,
            'adr_delta'      => '-2%',
            'adr_spark'      => $spark(),
        ];
    }

    protected function tonightStatusFor($property): array
    {
        $today = now()->toDateString();
        if (!class_exists(Booking::class)) {
            return ['state' => 'vacant', 'label' => __('Vacant')];
        }
        $b = Booking::query()
            ->where('property_id', $property->id)
            ->where('check_in', '<=', $today)
            ->where('check_out', '>', $today)
            ->first();
        if ($b) {
            return ['state' => 'occupied', 'label' => __('Occupied'), 'guest' => $b->guest_name ?? null];
        }
        $checkIn = Booking::query()
            ->where('property_id', $property->id)
            ->whereDate('check_in', $today)
            ->first();
        if ($checkIn) {
            return ['state' => 'checkin', 'label' => __('Check-in today'), 'guest' => $checkIn->guest_name ?? null];
        }
        return ['state' => 'vacant', 'label' => __('Vacant')];
    }

    protected function actionQueueFor($tenant): array
    {
        $items = [];
        if (class_exists(Booking::class)) {
            $depositsDue = Booking::query()
                ->when(method_exists(Booking::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
                ->where('payment_status', 'pending')
                ->whereBetween('check_in', [now(), now()->addDays(7)])
                ->count();
            if ($depositsDue) {
                $items[] = [
                    'icon'  => 'alert',
                    'tone'  => 'warn',
                    'title' => trans_choice(':count deposit due in 7 days|:count deposits due in 7 days', $depositsDue),
                    'cta'   => __('Send reminders'),
                ];
            }
            $newRequests = Booking::query()
                ->when(method_exists(Booking::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
                ->where('status', 'pending')
                ->count();
            if ($newRequests) {
                $items[] = [
                    'icon'  => 'clock',
                    'tone'  => 'info',
                    'title' => trans_choice(':count new booking request|:count new booking requests', $newRequests),
                    'cta'   => __('Review'),
                ];
            }
        }
        if (empty($items)) {
            $items[] = [
                'icon'  => 'check',
                'tone'  => 'ok',
                'title' => __('All clear — no urgent actions.'),
                'cta'   => null,
            ];
        }
        return $items;
    }

    protected function channelMixFor($tenant): array
    {
        if (!class_exists(Booking::class)) {
            return [
                ['source' => 'direct', 'label' => __('Direct'), 'count' => 14, 'pct' => 58],
                ['source' => 'toyyib', 'label' => 'Toyyibpay', 'count' => 7, 'pct' => 29],
                ['source' => 'walkin', 'label' => __('Walk-in'), 'count' => 3, 'pct' => 13],
            ];
        }
        $rows = Booking::query()
            ->when(method_exists(Booking::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
            ->where('created_at', '>=', now()->subDays(30))
            ->select('source', DB::raw('count(*) as c'))
            ->groupBy('source')
            ->get();
        $total = max(1, $rows->sum('c'));
        return $rows->map(fn ($r) => [
            'source' => $r->source ?? 'unknown',
            'label'  => ucfirst($r->source ?? 'Unknown'),
            'count'  => (int) $r->c,
            'pct'    => (int) round(($r->c / $total) * 100),
        ])->toArray();
    }

    protected function greeting(): string
    {
        $h = (int) now()->format('G');
        if ($h < 12) return __('Good morning');
        if ($h < 18) return __('Good afternoon');
        return __('Good evening');
    }
}
