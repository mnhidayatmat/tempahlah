<x-app-layout :title="__('Settings')">
    <div style="display:flex; flex-direction:column; gap: 20px; max-width: 880px;">

        {{-- Header --}}
        <div>
            <div class="kicker">{{ __('Workspace') }}</div>
            <div class="display-2" style="margin-top: 4px;">{{ __('Settings') }}</div>
            <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                {{ __('Tenant business info, taxes, MOTAC license and locale defaults.') }}
            </div>
        </div>

        {{-- Business info --}}
        <div class="hauz-card" style="padding: 22px;">
            <div class="kicker" style="margin-bottom: 14px;">{{ __('Business info') }}</div>
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                @foreach ([
                    [__('Business name'), $tenant->business_name],
                    [__('SSM number'), $tenant->ssm_number ?? '—'],
                    [__('Business email'), $tenant->business_email],
                    [__('Business phone'), $tenant->business_phone ?? '—'],
                    [__('Owner'), $tenant->owner?->name ?? '—'],
                    [__('Slug'), $tenant->slug],
                ] as [$label, $value])
                    <div>
                        <div class="kicker" style="font-size: 9.5px; margin-bottom: 4px;">{{ $label }}</div>
                        <div style="font-size: 13.5px; font-weight: 500;">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Taxes --}}
        <div class="hauz-card" style="padding: 22px;">
            <div class="kicker" style="margin-bottom: 14px;">{{ __('Malaysia tax & licensing') }}</div>
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 14px;">
                <div>
                    <div class="kicker" style="font-size: 9.5px; margin-bottom: 4px;">{{ __('SST registered') }}</div>
                    <div style="font-size: 13.5px; font-weight: 500;">
                        @if ($tenant->sst_registered)
                            <span class="pill pill-ok"><span class="pill-dot"></span>{{ __('Yes') }} ({{ number_format(((float) $tenant->sst_rate) * 100, 0) }}%)</span>
                        @else
                            <span class="pill"><span class="pill-dot"></span>{{ __('Not registered') }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="kicker" style="font-size: 9.5px; margin-bottom: 4px;">{{ __('Tourism tax') }}</div>
                    <div style="font-size: 13.5px; font-weight: 500;">RM 10/{{ __('night (foreign guests)') }}</div>
                </div>
                <div>
                    <div class="kicker" style="font-size: 9.5px; margin-bottom: 4px;">{{ __('MOTAC license') }}</div>
                    <div style="font-size: 13.5px; font-weight: 500;">
                        @if ($tenant->motac_license)
                            <span class="mono">{{ $tenant->motac_license }}</span>
                            @if ($tenant->motac_verified_at)
                                <span class="pill pill-ok" style="margin-left: 6px;"><span class="pill-dot"></span>{{ __('Verified') }}</span>
                            @else
                                <span class="pill pill-warn" style="margin-left: 6px;"><span class="pill-dot"></span>{{ __('Pending review') }}</span>
                            @endif
                        @else
                            <span style="color: var(--ink-3);">{{ __('Not provided') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Locale + branding --}}
        <div class="hauz-card" style="padding: 22px;">
            <div class="kicker" style="margin-bottom: 14px;">{{ __('Workspace defaults') }}</div>
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                <div>
                    <div class="kicker" style="font-size: 9.5px; margin-bottom: 4px;">{{ __('Default locale') }}</div>
                    <div style="font-size: 13.5px; font-weight: 500;">{{ strtoupper($tenant->default_locale ?? 'ms') }}</div>
                </div>
                <div>
                    <div class="kicker" style="font-size: 9.5px; margin-bottom: 4px;">{{ __('Plan') }}</div>
                    <div style="font-size: 13.5px; font-weight: 500;">
                        @if (($tenant->subscription?->plan ?? 'free') === 'free')
                            <span class="pill"><span class="pill-dot"></span>{{ __('Free') }}</span>
                        @else
                            <span class="pill pill-pro"><span class="pill-dot"></span>{{ __('Pro') }} · RM49/{{ __('mo') }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="kicker" style="font-size: 9.5px; margin-bottom: 4px;">{{ __('KYC status') }}</div>
                    <div style="font-size: 13.5px; font-weight: 500;">
                        @php $cls = match($tenant->kyc_status){ 'verified' => 'pill-ok', 'rejected' => 'pill-err', default => 'pill-warn' }; @endphp
                        <span class="pill {{ $cls }}"><span class="pill-dot"></span>{{ ucfirst($tenant->kyc_status) }}</span>
                    </div>
                </div>
                <div>
                    <div class="kicker" style="font-size: 9.5px; margin-bottom: 4px;">{{ __('Status') }}</div>
                    <div style="font-size: 13.5px; font-weight: 500;">
                        @php $cls = $tenant->status === 'active' ? 'pill-ok' : 'pill-warn'; @endphp
                        <span class="pill {{ $cls }}"><span class="pill-dot"></span>{{ ucfirst($tenant->status) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="padding: 14px 16px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 12.5px; color: var(--ink-3);">
            {{ __('Editing settings (business info, taxes, MOTAC license, branding) is wired to the database but the form UI is on the next milestone — for now, request changes via support.') }}
        </div>
    </div>
</x-app-layout>
