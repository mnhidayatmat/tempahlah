<x-app-layout :title="__('Platform Admin')" :subtitle="__('Subscribers overview')" :breadcrumbs="[__('Platform')]">
    @php
        $planBadge = function ($s) {
            if (! $s) return ['—', 'var(--ink-3)', 'var(--bg-sunk)'];
            return $s->plan === \App\Models\Subscription::PLAN_PAID
                ? [__('Paid'), 'var(--ok)', 'var(--ok-tint)']
                : [__('Free'), 'var(--ink-2)', 'var(--bg-sunk)'];
        };
        $statusBadge = function ($s) {
            $map = [
                \App\Models\Subscription::STATUS_TRIALING  => [__('Trialing'),  'var(--info)', 'var(--info-tint)'],
                \App\Models\Subscription::STATUS_ACTIVE    => [__('Active'),    'var(--ok)',   'var(--ok-tint)'],
                \App\Models\Subscription::STATUS_PAST_DUE  => [__('Past due'),  'var(--warn)', 'var(--warn-tint)'],
                \App\Models\Subscription::STATUS_CANCELLED => [__('Cancelled'), 'var(--err)',  'var(--err-tint)'],
            ];
            return $map[$s?->status] ?? [__('—'), 'var(--ink-3)', 'var(--bg-sunk)'];
        };
    @endphp

    <div style="display:flex; flex-direction:column; gap: 22px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Platform admin') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Subscribers') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ __('Free vs paid across every tenant on Tempahlah.') }}
                </div>
            </div>
            <a href="{{ route('tenant.dashboard') }}" class="btn btn-sm">
                <x-icon name="arrow-left" :size="12"/> {{ __('Back to my dashboard') }}
            </a>
        </div>

        {{-- Stat cards --}}
        <div style="display:grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap: 12px;">
            @php
                $cards = [
                    [__('Total tenants'), (string) $totalTenants, __('all workspaces'), null],
                    [__('Free plan'), (string) $free, $totalTenants > 0 ? round($free / max($totalTenants,1) * 100).'% of tenants' : __('none yet'), 'var(--ink-2)'],
                    [__('Subscribed'), (string) $subscribed, $trialing > 0 ? $paidActive.' '.__('active').' · '.$trialing.' '.__('trial') : $paidActive.' '.__('active'), 'var(--ok)'],
                    [__('On trial'), (string) $trialing, __('7-day paid trial'), 'var(--info)'],
                    [__('Past due'), (string) $pastDue, $cancelled > 0 ? $cancelled.' '.__('cancelled') : __('payment overdue'), $pastDue > 0 ? 'var(--warn)' : 'var(--ink-2)'],
                    [__('MRR'), 'RM '.number_format($mrr, 0), $paidActive.' '.__('active paid'), 'var(--primary)'],
                ];
            @endphp
            @foreach ($cards as [$label, $value, $sub, $tone])
                <div class="hauz-card" style="padding: 16px;">
                    <div class="kicker" style="margin-bottom: 6px;">{{ $label }}</div>
                    <div style="font-size: 26px; font-weight: 700; line-height: 1; color: {{ $tone ?? 'var(--ink)' }};">{{ $value }}</div>
                    <div style="margin-top: 5px; font-size: 11px; color: var(--ink-3);">{{ $sub }}</div>
                </div>
            @endforeach
        </div>

        {{-- Tenant list --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 16px 18px; border-bottom: .5px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
                <div>
                    <div style="font-weight: 600; font-size: 14px;">{{ __('All tenants') }}</div>
                    <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('Plan + status per workspace') }}</div>
                </div>
                <form method="GET" action="{{ route('platform.overview') }}" style="display:flex; gap: 8px; align-items:center; flex-wrap: wrap;">
                    <div style="display:flex; gap: 2px; background: var(--bg-sunk); padding: 3px; border-radius: var(--r-md);">
                        @foreach ([['', __('All')], ['free', __('Free')], ['paid', __('Paid')]] as [$val, $lbl])
                            @php $on = request()->query('plan', '') === $val; @endphp
                            <a href="{{ route('platform.overview', array_filter(['plan' => $val, 'q' => request('q')])) }}"
                               class="btn btn-sm" style="border:0; background: {{ $on ? 'var(--bg-elev)' : 'transparent' }}; color: {{ $on ? 'var(--primary)' : 'var(--ink-2)' }}; font-weight: {{ $on ? '600' : '500' }};">{{ $lbl }}</a>
                        @endforeach
                    </div>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name / email') }}" class="input" style="width: 200px; height: 34px;">
                </form>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 640px;">
                    <thead>
                        <tr style="background: var(--bg-sunk);">
                            @foreach ([__('Business'), __('Owner'), __('Plan'), __('Status'), __('Monthly'), __('Joined')] as $h)
                                <th style="text-align: left; padding: 10px 16px; font-weight: 500; font-size: 11px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .08em;">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $t)
                            @php
                                [$pl, $plColor, $plBg] = $planBadge($t->subscription);
                                [$stl, $stColor, $stBg] = $statusBadge($t->subscription);
                            @endphp
                            <tr style="border-top: .5px solid var(--line);">
                                <td style="padding: 12px 16px;">
                                    <div style="font-weight: 600;">{{ $t->business_name }}</div>
                                    <div class="mono" style="font-size: 11px; color: var(--ink-3);">{{ $t->slug }}.{{ config('app.tenant_domain') }}</div>
                                </td>
                                <td style="padding: 12px 16px; color: var(--ink-2);">
                                    {{ $t->owner?->name ?? '—' }}
                                    <div style="font-size: 11px; color: var(--ink-3);">{{ $t->business_email }}</div>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span class="pill" style="height:18px; font-size:10.5px; background: {{ $plBg }}; color: {{ $plColor }};">{{ $pl }}</span>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span class="pill" style="height:18px; font-size:10.5px; background: {{ $stBg }}; color: {{ $stColor }};">{{ $stl }}</span>
                                </td>
                                <td style="padding: 12px 16px;" class="mono">
                                    {{ $t->subscription && (float) $t->subscription->monthly_amount > 0 ? 'RM '.number_format($t->subscription->monthly_amount, 0) : '—' }}
                                </td>
                                <td style="padding: 12px 16px; color: var(--ink-3);" class="mono">{{ $t->created_at?->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="padding: 32px; text-align: center; color: var(--ink-3);">{{ __('No tenants match.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tenants->hasPages())
                <div style="padding: 14px 18px; border-top: .5px solid var(--line);">
                    {{ $tenants->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
