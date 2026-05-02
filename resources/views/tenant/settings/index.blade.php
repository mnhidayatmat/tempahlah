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

        @if ($errors->any())
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <strong style="display:block; margin-bottom: 6px;">{{ __('Please fix the following:') }}</strong>
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tenant.settings.update') }}" style="display:flex; flex-direction:column; gap: 18px;">
            @csrf
            @method('PATCH')

            {{-- Business info --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Business info') }}</div>
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Business name') }} *</label>
                        <input class="input" type="text" name="business_name" value="{{ old('business_name', $tenant->business_name) }}" required maxlength="120">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('SSM number') }}</label>
                        <input class="input" type="text" name="ssm_number" value="{{ old('ssm_number', $tenant->ssm_number) }}" maxlength="32">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Business email') }} *</label>
                        <input class="input" type="email" name="business_email" value="{{ old('business_email', $tenant->business_email) }}" required maxlength="160">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Business phone') }}</label>
                        <input class="input" type="text" name="business_phone" value="{{ old('business_phone', $tenant->business_phone) }}" maxlength="32" placeholder="+60 12-345 6789">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Owner') }}</label>
                        <input class="input" type="text" value="{{ $tenant->owner?->name ?? '—' }}" disabled style="opacity: .7;">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Slug') }}</label>
                        <input class="input" type="text" value="{{ $tenant->slug }}" disabled style="opacity: .7; font-family: var(--font-mono);">
                    </div>
                </div>
            </div>

            {{-- Taxes & licensing --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Malaysia tax & licensing') }}</div>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 14px; align-items: flex-start;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('SST registered') }}</label>
                        <label style="display:inline-flex; align-items:center; gap: 8px; padding-top: 8px; cursor:pointer; font-size: 13.5px;">
                            <input type="checkbox" name="sst_registered" value="1" {{ old('sst_registered', $tenant->sst_registered) ? 'checked' : '' }}>
                            <span>{{ __('Charge SST on bookings') }}</span>
                        </label>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('SST rate (decimal)') }}</label>
                        <input class="input" type="number" name="sst_rate" value="{{ old('sst_rate', number_format((float) $tenant->sst_rate, 4, '.', '')) }}" min="0" max="1" step="0.0001" placeholder="0.08">
                        <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('e.g. 0.08 = 8%') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('MOTAC license') }}</label>
                        <input class="input" type="text" name="motac_license" value="{{ old('motac_license', $tenant->motac_license) }}" maxlength="64" placeholder="MOT/A/B/C/123">
                        @if ($tenant->motac_license)
                            <div style="margin-top: 6px;">
                                @if ($tenant->motac_verified_at)
                                    <span class="pill pill-ok" style="height: 18px; font-size: 10.5px;"><span class="pill-dot"></span>{{ __('Verified') }}</span>
                                @else
                                    <span class="pill pill-warn" style="height: 18px; font-size: 10.5px;"><span class="pill-dot"></span>{{ __('Pending review') }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                <div style="margin-top: 14px; padding: 10px 12px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 11.5px; color: var(--ink-3);">
                    {{ __('Tourism Tax: RM 10/night automatically applied to foreign guests at registered accommodations.') }}
                </div>
            </div>

            {{-- Workspace defaults --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Workspace defaults') }}</div>
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Default locale') }}</label>
                        <select class="input" name="default_locale">
                            <option value="ms" {{ old('default_locale', $tenant->default_locale) === 'ms' ? 'selected' : '' }}>Bahasa Malaysia (BM)</option>
                            <option value="en" {{ old('default_locale', $tenant->default_locale) === 'en' ? 'selected' : '' }}>English (EN)</option>
                        </select>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Plan') }}</label>
                        <div style="padding-top: 8px;">
                            @if (($tenant->subscription?->plan ?? 'free') === 'free')
                                <span class="pill"><span class="pill-dot"></span>{{ __('Free') }}</span>
                                <a href="{{ route('tenant.subscription') }}" style="font-size: 12px; color: var(--primary); margin-left: 8px;">{{ __('Upgrade →') }}</a>
                            @else
                                <span class="pill pill-pro"><span class="pill-dot"></span>{{ __('Pro') }} · RM49/{{ __('mo') }}</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('KYC status') }}</label>
                        <div style="padding-top: 8px;">
                            @php $cls = match($tenant->kyc_status){ 'verified' => 'pill-ok', 'rejected' => 'pill-err', default => 'pill-warn' }; @endphp
                            <span class="pill {{ $cls }}"><span class="pill-dot"></span>{{ ucfirst($tenant->kyc_status) }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Status') }}</label>
                        <div style="padding-top: 8px;">
                            @php $cls = $tenant->status === 'active' ? 'pill-ok' : 'pill-warn'; @endphp
                            <span class="pill {{ $cls }}"><span class="pill-dot"></span>{{ ucfirst($tenant->status) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content: flex-end; gap: 8px;">
                <button type="reset" class="btn">{{ __('Discard') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
