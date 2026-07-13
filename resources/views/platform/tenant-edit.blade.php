<x-app-layout :title="__('Edit tenant')" :subtitle="$tenant->business_name" :breadcrumbs="[__('Platform'), __('Tenants')]">
    <div style="max-width: 680px; margin: 0 auto; display:flex; flex-direction:column; gap: 18px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Platform admin') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Edit tenant') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 13px;" class="mono">
                    {{ str_replace(['https://', 'http://'], '', $tenant->publicUrl()) }}
                </div>
            </div>
            <a href="{{ route('platform.overview') }}" class="btn btn-sm">
                <x-icon name="arrow-left" :size="12"/> {{ __('Back to list') }}
            </a>
        </div>

        @if ($errors->any())
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); color: var(--err);">
                {{ $errors->first() }}
            </div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err);">
                {{ session('error') }}
            </div>
        @endif

        {{-- Stripe-managed warning: an override here won't stop Stripe billing. --}}
        @if ($subscription?->isStripeManaged())
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--warn); background: var(--warn-tint);">
                <div style="font-weight: 600; font-size: 13px; margin-bottom: 3px;">{{ __('This tenant pays via Stripe') }}</div>
                <div style="font-size: 12.5px; color: var(--ink-2);">
                    {{ __('Changing the plan here only overrides Tempahlah access — it does NOT cancel their Stripe subscription. To stop billing, cancel it in Stripe (or have the host cancel from their subscription page).') }}
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('platform.tenants.update', $tenant->id) }}" style="display:flex; flex-direction:column; gap: 18px;">
            @csrf
            @method('PATCH')

            {{-- Business details --}}
            <div class="hauz-card" style="padding: 20px; display:flex; flex-direction:column; gap: 14px;">
                <div style="font-weight: 600; font-size: 14px;">{{ __('Business details') }}</div>

                <label style="display:flex; flex-direction:column; gap: 5px;">
                    <span style="font-size: 12px; color: var(--ink-2);">{{ __('Business name') }}</span>
                    <input type="text" name="business_name" required maxlength="160" class="input"
                           value="{{ old('business_name', $tenant->business_name) }}">
                </label>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <label style="display:flex; flex-direction:column; gap: 5px;">
                        <span style="font-size: 12px; color: var(--ink-2);">{{ __('Business email') }}</span>
                        <input type="email" name="business_email" maxlength="190" class="input"
                               value="{{ old('business_email', $tenant->business_email) }}">
                    </label>
                    <label style="display:flex; flex-direction:column; gap: 5px;">
                        <span style="font-size: 12px; color: var(--ink-2);">{{ __('Business phone') }}</span>
                        <input type="text" name="business_phone" maxlength="40" class="input"
                               value="{{ old('business_phone', $tenant->business_phone) }}">
                    </label>
                </div>

                <label style="display:flex; flex-direction:column; gap: 5px;">
                    <span style="font-size: 12px; color: var(--ink-2);">{{ __('Account status') }}</span>
                    <select name="status" class="input">
                        <option value="active" @selected(old('status', $tenant->status) === 'active')>{{ __('Active') }}</option>
                        <option value="suspended" @selected(old('status', $tenant->status) === 'suspended')>{{ __('Suspended (blocks their booking page)') }}</option>
                    </select>
                </label>
            </div>

            {{-- Plan --}}
            <div class="hauz-card" style="padding: 20px; display:flex; flex-direction:column; gap: 12px;">
                <div>
                    <div style="font-weight: 600; font-size: 14px;">{{ __('Plan') }}</div>
                    <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">
                        {{ __('“Pro” here is a complimentary grant — every paid feature, no billing. Use it for partners or offline payments.') }}
                    </div>
                </div>

                @php $plan = old('plan', $currentPlan); @endphp
                <div style="display:flex; flex-direction:column; gap: 10px;">
                    <label style="display:flex; gap: 10px; align-items:flex-start; padding: 12px 14px; border: 1px solid {{ $plan === 'free' ? 'var(--primary)' : 'var(--line)' }}; border-radius: var(--r-md); cursor:pointer;">
                        <input type="radio" name="plan" value="free" @checked($plan === 'free') style="margin-top: 2px;">
                        <span>
                            <span style="font-weight: 600; font-size: 13px;">{{ __('Free (Starter)') }}</span>
                            <span style="display:block; font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('1 homestay · 4 rooms · 20 bookings/month · manual payment only. Paid features switch off.') }}</span>
                        </span>
                    </label>
                    <label style="display:flex; gap: 10px; align-items:flex-start; padding: 12px 14px; border: 1px solid {{ $plan === 'pro' ? 'var(--primary)' : 'var(--line)' }}; border-radius: var(--r-md); cursor:pointer;">
                        <input type="radio" name="plan" value="pro" @checked($plan === 'pro') style="margin-top: 2px;">
                        <span>
                            <span style="font-weight: 600; font-size: 13px;">{{ __('Pro (complimentary)') }}</span>
                            <span style="display:block; font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('3 homestays · payment gateway, invoices, AI assistant, calendar sync, priority marketplace. Granted free, excluded from MRR.') }}</span>
                        </span>
                    </label>
                    <label style="display:flex; gap: 10px; align-items:flex-start; padding: 12px 14px; border: 1px solid {{ $plan === 'ultra' ? 'var(--primary)' : 'var(--line)' }}; border-radius: var(--r-md); cursor:pointer;">
                        <input type="radio" name="plan" value="ultra" @checked($plan === 'ultra') style="margin-top: 2px;">
                        <span>
                            <span style="font-weight: 600; font-size: 13px;">{{ __('Ultra (complimentary)') }}</span>
                            <span style="display:block; font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('Everything in Pro, unlimited homestays + staff, white-label, featured marketplace. Granted free, excluded from MRR.') }}</span>
                        </span>
                    </label>
                </div>

                @if ($subscription)
                    <div style="font-size: 11.5px; color: var(--ink-3); border-top: .5px solid var(--line); padding-top: 10px;">
                        {{ __('Currently:') }}
                        <span class="mono">{{ ucfirst($subscription->plan) }} · {{ $subscription->status }}</span>
                        @if ($subscription->isComped()) · {{ __('complimentary') }} @endif
                        @if ($subscription->onTrial()) · {{ __('on trial until :d', ['d' => $subscription->trial_ends_at?->format('d M Y')]) }} @endif
                    </div>
                @endif
            </div>

            <div style="display:flex; gap: 10px; justify-content:flex-end;">
                <a href="{{ route('platform.overview') }}" class="btn">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
            </div>
        </form>

        {{-- Danger zone: delete tenant (soft delete — reversible by support). --}}
        <div class="hauz-card" style="padding: 18px 20px; border-color: var(--err);">
            <div style="font-weight: 600; color: var(--err); margin-bottom: 4px;">{{ __('Danger zone') }}</div>
            <div style="font-size: 13px; color: var(--ink-3); line-height: 1.5; margin-bottom: 12px;">
                {{ __('Deleting removes this homestay from the platform and takes its public booking page offline. Its data is retained and can be restored by support if needed.') }}
            </div>
            <form method="POST" action="{{ route('platform.tenants.destroy', $tenant->id) }}"
                  onsubmit="return prompt(@js(__('Type the homestay name to confirm deletion:'))) === @js($tenant->business_name);">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm" style="color: var(--err); border-color: var(--err);">
                    {{ __('Delete this homestay') }}
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
