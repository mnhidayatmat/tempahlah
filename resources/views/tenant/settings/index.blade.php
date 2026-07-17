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

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--ok-tint); color: var(--ok); border-color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--err-tint); color: var(--err); border-color: var(--err); font-size: 13px;">{{ session('error') }}</div>
        @endif

        {{-- ─── Getting started ────────────────────────────────────────
             The onboarding tour's own last step promises hosts they "can always
             replay this tour from Settings". Until this card existed, nothing
             ever cleared users.tour_completed_at, so that promise was a lie. --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
                <div>
                    <div class="kicker">{{ __('Getting started') }}</div>
                    <div style="margin-top: 4px; font-size: 13.5px; color: var(--ink);">{{ __('App walkthrough') }}</div>
                    <div style="margin-top: 2px; font-size: 12.5px; color: var(--ink-3);">
                        @if (auth()->user()?->tour_completed_at)
                            {{ __('You finished it :when. Replay it any time.', ['when' => auth()->user()->tour_completed_at->diffForHumans()]) }}
                        @else
                            {{ __('It will play the next time you open your dashboard.') }}
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('tenant.onboarding.replay') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm">
                        <x-icon name="sparkle" :size="12"/> {{ __('Replay walkthrough') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- ─── Your homestays ─────────────────────────────────────── --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 14px; gap: 12px;">
                <div>
                    <div class="kicker">{{ __('Your homestays') }}</div>
                    <div style="margin-top: 2px; font-size: 12.5px; color: var(--ink-3);">
                        {{ trans_choice('{0} No properties created yet.|{1} :count property|[2,*] :count properties', $properties->count(), ['count' => $properties->count()]) }}
                    </div>
                </div>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-sm btn-primary">
                    <x-icon name="plus" :size="12"/> {{ __('Add homestay') }}
                </a>
            </div>

            @if ($properties->isEmpty())
                <div style="padding: 24px; text-align: center; color: var(--ink-3); font-size: 13px; background: var(--bg-elev); border-radius: var(--r-md);">
                    {{ __('You have not created any homestays yet.') }}
                </div>
            @else
                <div style="display:flex; flex-direction:column; gap: 8px;">
                    @foreach ($properties as $p)
                        @php
                            $startRate = (float) ($p->rooms->min('base_price') ?? 0);
                            $statusCls = match($p->status){ 'active' => 'pill-ok', 'archived' => 'pill-warn', default => '' };
                        @endphp
                        <div style="display:grid; grid-template-columns: 1fr auto; align-items:center; gap: 12px; padding: 14px 16px; background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--r-md);">
                            <div style="min-width: 0;">
                                <div style="display:flex; align-items:center; gap: 10px; flex-wrap: wrap;">
                                    <a href="{{ route('tenant.properties.show', ['id' => $p->id]) }}" style="font-weight: 600; color: var(--ink); text-decoration: none; font-size: 14.5px; letter-spacing: -0.01em;">{{ $p->name }}</a>
                                    <span class="pill {{ $statusCls }}" style="height: 18px; font-size: 10.5px;"><span class="pill-dot"></span>{{ ucfirst($p->status) }}</span>
                                </div>
                                <div style="margin-top: 4px; font-size: 12px; color: var(--ink-3); display:flex; gap: 10px; flex-wrap: wrap; align-items: baseline;">
                                    @if ($p->city)<span>📍 {{ $p->city }}{{ $p->state ? ', '.$p->state : '' }}</span><span style="color: var(--ink-4);">·</span>@endif
                                    <span>{{ $p->rooms->count() }} {{ trans_choice('room|rooms', $p->rooms->count()) }}</span>
                                    <span style="color: var(--ink-4);">·</span>
                                    <span style="font-family: var(--font-mono);">RM {{ number_format($startRate, 0) }}/{{ __('night') }}</span>
                                </div>
                            </div>
                            <div style="display:flex; gap: 6px; align-items: center;">
                                <a href="{{ route('tenant.properties.edit', ['property' => $p->public_id]) }}" class="btn btn-sm" title="{{ __('Edit homestay') }}">
                                    <x-icon name="cog" :size="12"/> {{ __('Edit') }}
                                </a>
                                <form method="POST" action="{{ route('tenant.properties.destroy', ['property' => $p->public_id]) }}" style="display:inline;"
                                      onsubmit="return confirm('{{ __('Delete :name? This cannot be undone if any bookings reference it.', ['name' => addslashes($p->name)]) }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm" title="{{ __('Delete homestay') }}" style="color: var(--err); border-color: var(--err); background: transparent;">
                                        <x-icon name="x" :size="12"/> {{ __('Delete') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

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
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Business phone (WhatsApp)') }}</label>
                        <input class="input" type="tel" inputmode="tel" name="business_phone" value="{{ old('business_phone', $tenant->business_phone) }}" maxlength="32" placeholder="+60123456789" data-phone-input>
                        <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('The WhatsApp number guests message from your public booking page.') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Owner') }} *</label>
                        <input class="input" type="text" name="owner_name" value="{{ old('owner_name', $tenant->owner?->name) }}" required maxlength="120">
                    </div>
                    <div x-data="{
                            slug: '{{ old('slug', $tenant->slug) }}',
                            original: '{{ $tenant->slug }}',
                            status: '',
                            message: '',
                            timer: null,
                            sanitize() {
                                this.slug = this.slug
                                    .toLowerCase()
                                    .replace(/[^a-z0-9-]/g, '-')
                                    .replace(/--+/g, '-')
                                    .replace(/^-+|-+$/g, '');
                                this.check();
                            },
                            check() {
                                clearTimeout(this.timer);
                                if (!this.slug || this.slug === this.original) {
                                    this.status = this.slug === this.original ? 'current' : '';
                                    this.message = '';
                                    return;
                                }
                                this.status = 'checking';
                                this.message = '{{ __('Checking availability…') }}';
                                this.timer = setTimeout(async () => {
                                    try {
                                        const r = await fetch('{{ route('tenant.settings.slug-available') }}?slug=' + encodeURIComponent(this.slug), { headers: { 'Accept': 'application/json' } });
                                        const j = await r.json();
                                        this.status = j.status;
                                        this.message = j.message;
                                    } catch (e) { this.status = ''; this.message = ''; }
                                }, 350);
                            },
                            color() {
                                return { available:'var(--ok)', current:'var(--ink-3)', checking:'var(--ink-3)',
                                         taken:'var(--err)', reserved:'var(--err)', invalid:'var(--err)' }[this.status] || 'var(--ink-3)';
                            },
                         }">
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Booking-page URL slug') }}</label>
                        <input class="input" type="text" name="slug"
                               x-model="slug"
                               @input="sanitize()"
                               :style="'font-family: var(--font-mono); text-transform: lowercase;' + ((status==='taken'||status==='reserved'||status==='invalid') ? ' border-color: var(--err);' : (status==='available' ? ' border-color: var(--ok);' : ''))"
                               minlength="2" maxlength="60" required
                               pattern="[a-z0-9]+(-[a-z0-9]+)*">
                        @error('slug')
                            <div style="font-size: 11.5px; color: var(--err); margin-top: 4px;">{{ $message }}</div>
                        @enderror

                        {{-- Live availability result --}}
                        <div x-show="message" x-cloak :style="'font-size: 11.5px; margin-top: 5px; display:flex; align-items:center; gap:5px; color:' + color()">
                            <span x-show="status==='checking'">⏳</span>
                            <span x-show="status==='available'">✓</span>
                            <span x-show="status==='taken' || status==='reserved' || status==='invalid'">✗</span>
                            <span x-text="message"></span>
                        </div>

                        {{-- Live URL preview --}}
                        <div style="font-size: 11.5px; color: var(--ink-3); margin-top: 5px; line-height: 1.4;">
                            {{ __('Your booking page:') }}
                            <span class="mono" style="color: var(--ink); background: var(--bg-sunk); padding: 1px 5px; border-radius: 3px;">
                                <span x-text="slug || 'your-slug'"></span>.tempahlah.com
                            </span>
                        </div>

                        {{-- Warning when changing --}}
                        <div x-show="slug && slug !== original" x-cloak
                             style="margin-top: 8px; padding: 7px 10px; background: var(--warn-tint); color: var(--warn); border-radius: 6px; font-size: 11px; line-height: 1.4;">
                            ⚠️ {{ __('Saving will change your booking page URL. The old') }}
                            <span class="mono" x-text="original + '.tempahlah.com'"></span>
                            {{ __('will stop working — be sure to update any links you have shared with guests.') }}
                        </div>
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

            {{-- Payment & refund policy --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 4px;">{{ __('Payment & refund policy') }}</div>
                <p style="font-size: 12px; color: var(--ink-3); margin: 0 0 14px;">
                    {{ __('Controls automatic payment reminders + cancellation of unpaid bookings, and the refund terms guests see when they book.') }}
                </p>

                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 14px; align-items: flex-start;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Full payment due (days before check-in)') }}</label>
                        <input class="input" type="number" name="full_payment_days_before" min="0" max="60" step="1"
                               value="{{ old('full_payment_days_before', $tenant->fullPaymentDaysBefore()) }}">
                        <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Balance reminder (email + WhatsApp) fires this many days before arrival.') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Booking-fee pay window (hours)') }}</label>
                        <input class="input" type="number" name="fee_payment_hours" min="1" max="336" step="1"
                               value="{{ old('fee_payment_hours', $tenant->feePaymentHours()) }}">
                        <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Unpaid bookings auto-cancel after this. e.g. 24 = 1 day.') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Cancel unpaid balance on') }}</label>
                        <select class="input" name="cancel_balance_on">
                            <option value="check_in" {{ old('cancel_balance_on', $tenant->cancelBalanceOn()) === 'check_in' ? 'selected' : '' }}>{{ __('Check-in day') }}</option>
                            <option value="due_date" {{ old('cancel_balance_on', $tenant->cancelBalanceOn()) === 'due_date' ? 'selected' : '' }}>{{ __('Payment due date') }}</option>
                        </select>
                        <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Only applies if auto-cancel below is on.') }}</div>
                    </div>
                </div>

                <label style="display:flex; align-items:flex-start; gap: 10px; margin-top: 16px; cursor: pointer;">
                    <input type="hidden" name="auto_cancel_unpaid_balance" value="0">
                    <input type="checkbox" name="auto_cancel_unpaid_balance" value="1" style="margin-top: 2px;"
                           {{ old('auto_cancel_unpaid_balance', $tenant->autoCancelUnpaidBalance()) ? 'checked' : '' }}>
                    <span>
                        <span style="font-size: 13px; font-weight: 600;">{{ __('Auto-cancel deposit-paid bookings if the balance is unpaid') }}</span>
                        <span style="display:block; font-size: 11px; color: var(--ink-3); margin-top: 3px;">
                            {{ __('Leave OFF if you collect the balance on arrival (recommended). When ON, a confirmed booking whose balance is still unpaid is cancelled on the date set above — and its dates are freed.') }}
                        </span>
                    </span>
                </label>

                <label style="display:flex; align-items:flex-start; gap: 10px; margin-top: 16px; cursor: pointer;">
                    <input type="hidden" name="deposit_is_security" value="0">
                    <input type="checkbox" name="deposit_is_security" value="1" style="margin-top: 2px;"
                           {{ old('deposit_is_security', $tenant->depositIsSecurity()) ? 'checked' : '' }}>
                    <span>
                        <span style="font-size: 13px; font-weight: 600;">{{ __('Deposit is a refundable security deposit') }}</span>
                        <span style="display:block; font-size: 11px; color: var(--ink-3); margin-top: 3px;">
                            {{ __('When ON, the guest is asked to pay the FULL stay total before check-in (the deposit is not deducted), and you refund the deposit after check-out. Example: total RM 1,600 with an RM 100 deposit already paid — the payment reminder asks for RM 1,600, not RM 1,500. Leave OFF to have the reminder chase only the remaining balance (total minus deposit).') }}
                        </span>
                    </span>
                </label>

                <div style="margin-top: 16px;">
                    <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Refund / return policy') }}</label>
                    <div style="margin-bottom: 8px; padding: 10px 12px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 12px; color: var(--ink-2);">
                        <strong>{{ __('Always applies:') }}</strong> {{ __(\App\Models\Tenant::DEFAULT_REFUND_POLICY) }}
                    </div>
                    <textarea class="input" name="refund_policy" rows="3" maxlength="2000"
                              style="height:auto; padding:10px 12px; resize:vertical;"
                              placeholder="{{ __('Add any extra refund terms here (optional), e.g. balance refundable up to 7 days before check-in.') }}">{{ old('refund_policy', $tenant->refund_policy) }}</textarea>
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Shown to guests at booking time and printed on their invoice.') }}</div>
                </div>
            </div>

            {{-- Manual payment (bank transfer / cash). Anchored (#payment-setup) so the
                 onboarding checklist's "Tell guests how to pay you" step lands the host
                 directly here, not at the top of the page. scroll-margin-top clears the
                 64px sticky topbar so the heading isn't hidden under it. --}}
            <div id="payment-setup" class="hauz-card" style="padding: 22px; scroll-margin-top: 88px;">
                <div class="kicker" style="margin-bottom: 4px;">{{ __('Manual payment (bank transfer / cash)') }}</div>
                <p style="font-size: 12px; color: var(--ink-3); margin: 0 0 14px;">
                    {{ __('Your booking page always lets guests choose to pay you directly instead of through the online gateway. They still get an invoice; you mark the booking fee / full payment as paid in the booking, which sends them a receipt.') }}
                </p>

                <div style="margin-top: 16px;">
                    <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Payment instructions shown to guests') }}</label>
                    <textarea class="input" name="manual_payment_instructions" rows="4" maxlength="2000"
                              style="height:auto; padding:10px 12px; resize:vertical;"
                              placeholder="{{ __("e.g.\nMaybank 5123 4567 8901 (Wafa Homestay Sdn Bhd)\nDuitNow: 012-345 6789\nWhatsApp the transfer receipt to confirm.") }}">{{ old('manual_payment_instructions', $tenant->manual_payment_instructions) }}</textarea>
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Printed on the guest\'s invoice and shown on the booking-received page. If left blank, guests are told to contact you to arrange payment.') }}</div>
                </div>
            </div>

            {{-- Convenience Save button here so the host can save right after the
                 payment settings without scrolling to the bottom. Same form, so it
                 saves everything on the page. --}}
            <div style="display:flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
            </div>

            {{-- Check-out reminder — auto WhatsApp = a Pro (auto_reminders) feature. --}}
            @php $canAutoRemind = \Laravel\Pennant\Feature::active('auto_reminders'); @endphp
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 4px;">{{ __('Check-out reminder') }}</div>
                <p style="font-size: 12px; color: var(--ink-3); margin: 0 0 14px;">
                    {{ __('Automatically WhatsApp the guest your check-out guidelines a few hours before they leave (clean up, take out the rubbish, lock up, etc.). Sends only if your WhatsApp is connected.') }}
                </p>

                @unless ($canAutoRemind)
                    <div style="display:flex; align-items:center; gap:10px; margin: 0 0 16px; padding:12px 14px; border-radius:var(--r-md); background:var(--pro-tint); color:var(--pro); font-size:12.5px;">
                        <x-icon name="lock" :size="14"/>
                        <span style="flex:1; min-width:0;">
                            {{ __('Automatic check-out reminders are a Pro feature.') }}
                        </span>
                        <a href="{{ route('tenant.subscription') }}" class="btn btn-sm">{{ __('Upgrade') }} →</a>
                    </div>
                @endunless

                <fieldset @disabled(! $canAutoRemind) style="border:0; padding:0; margin:0; {{ $canAutoRemind ? '' : 'opacity:.55;' }}">
                <label style="display:flex; align-items:flex-start; gap: 10px; cursor: {{ $canAutoRemind ? 'pointer' : 'not-allowed' }};">
                    <input type="hidden" name="checkout_reminder_enabled" value="0">
                    <input type="checkbox" name="checkout_reminder_enabled" value="1" style="margin-top: 2px;"
                           @disabled(! $canAutoRemind)
                           {{ old('checkout_reminder_enabled', $tenant->checkoutReminderEnabled()) ? 'checked' : '' }}>
                    <span>
                        <span style="font-size: 13px; font-weight: 600;">{{ __('Send a check-out reminder on WhatsApp') }}</span>
                        <span style="display:block; font-size: 11px; color: var(--ink-3); margin-top: 3px;">
                            {{ __('Turn off if you’d rather remind guests yourself.') }}
                        </span>
                    </span>
                </label>

                <div style="margin-top: 16px; max-width: 280px;">
                    <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Hours before check-out') }}</label>
                    <input class="input" type="number" name="checkout_reminder_hours" min="1" max="72" step="1"
                           @disabled(! $canAutoRemind)
                           value="{{ old('checkout_reminder_hours', $tenant->checkoutReminderHours()) }}">
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('e.g. 3 = sent 3 hours before the property’s check-out time.') }}</div>
                </div>

                <div style="margin-top: 16px;">
                    <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Check-out guidelines message') }}</label>
                    <textarea class="input" name="checkout_reminder_message" rows="6"
                              @disabled(! $canAutoRemind)
                              style="height:auto; padding:10px 12px; resize:vertical;"
                              placeholder="{{ __(\App\Models\Tenant::DEFAULT_CHECKOUT_MESSAGE) }}">{{ old('checkout_reminder_message', $tenant->checkout_reminder_message) }}</textarea>
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Leave blank to use the default guidelines shown above. A greeting and your check-out time are added automatically.') }}</div>
                </div>
                </fieldset>
            </div>

            {{-- Housekeeping automation — auto-scheduling = a Pro (auto_operational_tasks) feature. --}}
            @php $canAutoHousekeep = \Laravel\Pennant\Feature::active('auto_operational_tasks'); @endphp
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 4px;">{{ __('Housekeeping automation') }}</div>
                <p style="font-size: 12px; color: var(--ink-3); margin: 0 0 14px;">
                    {{ __('Automatically schedule cleaning + laundry from your bookings, following a typical homestay routine — no manual setup per booking.') }}
                </p>

                @unless ($canAutoHousekeep)
                    <div style="display:flex; align-items:center; gap:10px; margin: 0 0 16px; padding:12px 14px; border-radius:var(--r-md); background:var(--pro-tint); color:var(--pro); font-size:12.5px;">
                        <x-icon name="lock" :size="14"/>
                        <span style="flex:1; min-width:0;">
                            {{ __('Auto-scheduling cleaning & laundry is a Pro feature. On the free plan, schedule housekeeping tasks by hand in Housekeeping.') }}
                        </span>
                        <a href="{{ route('tenant.subscription') }}" class="btn btn-sm">{{ __('Upgrade') }} →</a>
                    </div>
                @endunless

                <fieldset @disabled(! $canAutoHousekeep) style="border:0; padding:0; margin:0; {{ $canAutoHousekeep ? '' : 'opacity:.55;' }}">
                <label style="display:flex; align-items:flex-start; gap: 10px; cursor: {{ $canAutoHousekeep ? 'pointer' : 'not-allowed' }};">
                    <input type="hidden" name="auto_housekeeping" value="0">
                    <input type="checkbox" name="auto_housekeeping" value="1" style="margin-top: 2px;"
                           @disabled(! $canAutoHousekeep)
                           {{ old('auto_housekeeping', $tenant->autoHousekeepingEnabled()) ? 'checked' : '' }}>
                    <span>
                        <span style="font-size: 13px; font-weight: 600;">{{ __('Auto-schedule cleaning & laundry from bookings') }}</span>
                        <span style="display:block; font-size: 11px; color: var(--ink-3); margin-top: 3px;">
                            {{ __('Turn off to schedule every housekeeping task by hand instead.') }}
                        </span>
                    </span>
                </label>
                </fieldset>

                <div style="margin-top: 14px; padding: 12px 14px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 11.5px; color: var(--ink-2); line-height: 1.6;">
                    <div style="font-weight: 600; margin-bottom: 4px;">{{ __('When a booking is confirmed, the system will:') }}</div>
                    <div>🧹 {{ __('Schedule a full clean 30 min after check-out.') }}</div>
                    <div>👥 {{ __('Request 2 cleaners (~2h) when the next guest arrives within 2 days — otherwise 1 cleaner (~4h).') }}</div>
                    <div>🧺 {{ __('Send the linen batch for laundry after check-out.') }}</div>
                    <div>🪶 {{ __('Add a pre-arrival dusting (~2h) when the house has sat empty 3+ days before check-in.') }}</div>
                    <div style="color: var(--ink-3); margin-top: 6px;">{{ __('You can still edit or reassign any task in Housekeeping.') }}</div>
                </div>
            </div>

            {{-- Housekeeping auto-complete + typical costs — available on EVERY
                 plan (unlike auto-scheduling above, which is Pro), so this card
                 is never disabled. --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 4px;">{{ __('Auto-complete & typical costs') }}</div>
                <p style="font-size: 12px; color: var(--ink-3); margin: 0 0 14px;">
                    {{ __('Let the system tick off cleaning + laundry you forget, and record a typical price so the job still counts toward your costs and reports.') }}
                </p>

                <label style="display:flex; align-items:flex-start; gap: 10px; cursor: pointer;">
                    <input type="hidden" name="auto_complete_housekeeping" value="0">
                    <input type="checkbox" name="auto_complete_housekeeping" value="1" style="margin-top: 2px;"
                           {{ old('auto_complete_housekeeping', $tenant->autoCompleteHousekeepingEnabled()) ? 'checked' : '' }}>
                    <span>
                        <span style="font-size: 13px; font-weight: 600;">{{ __('Auto start & complete tasks I forget to tick') }}</span>
                        <span style="display:block; font-size: 11px; color: var(--ink-3); margin-top: 3px;">
                            {{ __('A cleaning is marked done after its scheduled time + duration; a laundry batch after its expected return. Turn off to tick every task by hand.') }}
                        </span>
                    </span>
                </label>

                <div style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 14px; margin-top: 16px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Typical cleaning cost (RM)') }}</label>
                        <input class="input" type="number" name="default_cleaning_cost" min="0" max="100000" step="0.01"
                               value="{{ old('default_cleaning_cost', number_format($tenant->defaultCleaningCost(), 2, '.', '')) }}">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Typical laundry cost (RM)') }}</label>
                        <input class="input" type="number" name="default_laundry_cost" min="0" max="100000" step="0.01"
                               value="{{ old('default_laundry_cost', number_format($tenant->defaultLaundryCost(), 2, '.', '')) }}">
                    </div>
                </div>
                <p style="font-size: 11px; color: var(--ink-3); margin: 10px 0 0;">
                    {{ __('Applied only when a task is marked done and no cost was entered — a job\'s own cost always wins. Set 0 to record nothing.') }}
                </p>
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

            {{-- ─── Brand & theme ──────────────────────────────────────── --}}
            @php
                $defaults = \App\Models\Tenant::THEME_DEFAULTS;
                $brand = [
                    'primary'   => old('primary_color',   $tenant->primary_color   ?? $defaults['primary']),
                    'secondary' => old('secondary_color', $tenant->secondary_color ?? $defaults['secondary']),
                    'accent'    => old('accent_color',    $tenant->accent_color    ?? $defaults['accent']),
                ];
                $presets = [
                    ['key' => 'tempahlah', 'label' => __('Tempahlah Teal'),      'primary' => '#2596c6', 'secondary' => '#2cb8c4', 'accent' => '#e8b94a'],
                    ['key' => 'coastal',   'label' => __('Coastal Blue'),        'primary' => '#2e7da6', 'secondary' => '#1e4d6b', 'accent' => '#5db4d6'],
                    ['key' => 'highland',  'label' => __('Highland Green'),      'primary' => '#4a7a4a', 'secondary' => '#2d5230', 'accent' => '#88a86b'],
                    ['key' => 'heritage',  'label' => __('Heritage Burgundy'),   'primary' => '#8b3a3a', 'secondary' => '#5a1f1f', 'accent' => '#c47e6e'],
                    ['key' => 'sunset',    'label' => __('Sunset Orange'),       'primary' => '#d97757', 'secondary' => '#a8401e', 'accent' => '#d4a437'],
                    ['key' => 'charcoal',  'label' => __('Modern Charcoal'),     'primary' => '#2d2d2d', 'secondary' => '#1a1a1a', 'accent' => '#2596c6'],
                    ['key' => 'tropical',  'label' => __('Tropical Teal'),       'primary' => '#1e8a8a', 'secondary' => '#0e5c5c', 'accent' => '#d4a437'],
                ];
                $canBrandTheme = \Laravel\Pennant\Feature::active('brand_theme');
            @endphp

            @if ($canBrandTheme)
            <div class="hauz-card" style="padding: 22px;"
                 x-data="{
                    primary:   '{{ $brand['primary'] }}',
                    secondary: '{{ $brand['secondary'] }}',
                    accent:    '{{ $brand['accent'] }}',
                    showPreview: false,
                    apply(p) { this.primary = p.primary; this.secondary = p.secondary; this.accent = p.accent; },
                    reset() { this.apply({{ json_encode($defaults) }}); },
                    isValid(v) { return /^#[0-9a-fA-F]{6}$/.test(v); },
                    contrastInk(hex) {
                        if (!this.isValid(hex)) return '#fff';
                        const h = hex.replace('#','');
                        const r = parseInt(h.substr(0,2),16), g = parseInt(h.substr(2,2),16), b = parseInt(h.substr(4,2),16);
                        return ((r*299 + g*587 + b*114) / 1000) >= 165 ? '#1a1614' : '#ffffff';
                    },
                 }">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom: 16px;">
                    <div>
                        <div class="kicker">{{ __('Brand & theme') }}</div>
                        <div style="margin-top: 4px; font-size: 13px; color: var(--ink-2); max-width: 520px;">
                            {{ __('Pick a brand palette. These colors flow through your dashboard chrome and the public booking page guests see at') }}
                            <span class="mono" style="color: var(--ink); background: var(--bg-sunk); padding: 2px 6px; border-radius: 4px; font-size: 11.5px;">{{ str_replace(['https://','http://'], '', $tenant->publicUrl()) }}</span>.
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost" @click="reset()" style="color: var(--ink-3); flex-shrink: 0;">
                        {{ __('Reset to default') }}
                    </button>
                </div>

                {{-- Preset palettes --}}
                <div class="kicker" style="font-size: 9.5px; margin-bottom: 8px;">{{ __('Quick presets') }}</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 10px; margin-bottom: 22px;">
                    @foreach ($presets as $p)
                        <button type="button"
                                @click="apply({{ json_encode($p) }})"
                                :class="primary === '{{ $p['primary'] }}' && secondary === '{{ $p['secondary'] }}' && accent === '{{ $p['accent'] }}' ? 'is-active' : ''"
                                style="text-align:left; padding: 12px; border-radius: var(--r-md); border: 1.5px solid var(--line); background: var(--bg-elev); cursor:pointer; transition: all 120ms; display:flex; flex-direction:column; gap: 10px;"
                                onmouseover="this.style.borderColor='var(--ink-4)'" onmouseout="this.style.borderColor=primary==='{{ $p['primary'] }}'&&secondary==='{{ $p['secondary'] }}'&&accent==='{{ $p['accent'] }}'?'var(--primary)':'var(--line)'"
                                x-bind:style="primary === '{{ $p['primary'] }}' && secondary === '{{ $p['secondary'] }}' && accent === '{{ $p['accent'] }}' ? 'border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent);' : ''">
                            <div style="display:flex; gap: 4px; align-items:center;">
                                <span style="width: 28px; height: 28px; border-radius: 6px; background: {{ $p['primary'] }}; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);"></span>
                                <span style="width: 22px; height: 22px; border-radius: 5px; background: {{ $p['secondary'] }}; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);"></span>
                                <span style="width: 18px; height: 18px; border-radius: 4px; background: {{ $p['accent'] }}; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);"></span>
                            </div>
                            <div style="font-size: 12px; font-weight: 600; color: var(--ink); letter-spacing: -0.005em;">{{ $p['label'] }}</div>
                        </button>
                    @endforeach
                </div>

                {{-- Custom color inputs --}}
                <div class="kicker" style="font-size: 9.5px; margin-bottom: 8px;">{{ __('Custom colors') }}</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 22px;">
                    {{-- Primary --}}
                    <div>
                        <div style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                            {{ __('Primary') }}
                            <span style="font-size: 10.5px; font-weight: 500; color: var(--ink-3);">· {{ __('CTAs, links, highlights') }}</span>
                        </div>
                        <div style="display:flex; gap: 8px; align-items:center;">
                            <button type="button"
                                    @click="$refs.primaryPicker.click()"
                                    aria-label="{{ __('Pick primary color') }}"
                                    style="width: 44px; height: 36px; border-radius: var(--r-md); border: 1px solid var(--line-2); cursor:pointer; flex-shrink: 0; padding: 0;"
                                    :style="`background: ${primary}`"></button>
                            <input type="color" x-model="primary" x-ref="primaryPicker" tabindex="-1" aria-hidden="true" style="position:absolute; opacity:0; width:1px; height:1px; pointer-events:none; left:-9999px;">
                            <input class="input mono" type="text" name="primary_color" x-model="primary" value="{{ $brand['primary'] }}" maxlength="7" placeholder="#2596c6" style="text-transform: lowercase;">
                        </div>
                    </div>

                    {{-- Secondary --}}
                    <div>
                        <div style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                            {{ __('Secondary') }}
                            <span style="font-size: 10.5px; font-weight: 500; color: var(--ink-3);">· {{ __('Chips, accents, deep tones') }}</span>
                        </div>
                        <div style="display:flex; gap: 8px; align-items:center;">
                            <button type="button"
                                    @click="$refs.secondaryPicker.click()"
                                    aria-label="{{ __('Pick secondary color') }}"
                                    style="width: 44px; height: 36px; border-radius: var(--r-md); border: 1px solid var(--line-2); cursor:pointer; flex-shrink: 0; padding: 0;"
                                    :style="`background: ${secondary}`"></button>
                            <input type="color" x-model="secondary" x-ref="secondaryPicker" tabindex="-1" aria-hidden="true" style="position:absolute; opacity:0; width:1px; height:1px; pointer-events:none; left:-9999px;">
                            <input class="input mono" type="text" name="secondary_color" x-model="secondary" value="{{ $brand['secondary'] }}" maxlength="7" placeholder="#2cb8c4" style="text-transform: lowercase;">
                        </div>
                    </div>

                    {{-- Accent --}}
                    <div>
                        <div style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                            {{ __('Accent') }}
                            <span style="font-size: 10.5px; font-weight: 500; color: var(--ink-3);">· {{ __('Badges, pricing emphasis') }}</span>
                        </div>
                        <div style="display:flex; gap: 8px; align-items:center;">
                            <button type="button"
                                    @click="$refs.accentPicker.click()"
                                    aria-label="{{ __('Pick accent color') }}"
                                    style="width: 44px; height: 36px; border-radius: var(--r-md); border: 1px solid var(--line-2); cursor:pointer; flex-shrink: 0; padding: 0;"
                                    :style="`background: ${accent}`"></button>
                            <input type="color" x-model="accent" x-ref="accentPicker" tabindex="-1" aria-hidden="true" style="position:absolute; opacity:0; width:1px; height:1px; pointer-events:none; left:-9999px;">
                            <input class="input mono" type="text" name="accent_color" x-model="accent" value="{{ $brand['accent'] }}" maxlength="7" placeholder="#d4a437" style="text-transform: lowercase;">
                        </div>
                    </div>
                </div>

                {{-- Live preview (collapsed by default) --}}
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 8px;">
                    <div class="kicker" style="font-size: 9.5px;">{{ __('Live preview') }}</div>
                    <button type="button" class="btn btn-sm btn-ghost" @click="showPreview = !showPreview" style="color: var(--ink-3); font-size: 11.5px;">
                        <span x-text="showPreview ? '{{ __('Hide preview') }}' : '{{ __('Show preview') }}'">{{ __('Show preview') }}</span>
                    </button>
                </div>
                <div x-show="showPreview" x-cloak style="border-radius: var(--r-lg); border: 1px solid var(--line-2); overflow:hidden; background: var(--bg-sunk);">
                    {{-- Mini hero --}}
                    <div :style="`background: radial-gradient(ellipse at 20% 30%, color-mix(in srgb, ${primary} 65%, transparent) 0%, transparent 55%), radial-gradient(ellipse at 80% 70%, color-mix(in srgb, ${secondary} 50%, transparent) 0%, transparent 55%), linear-gradient(135deg, ${secondary} 0%, ${primary} 45%, color-mix(in srgb, ${accent} 50%, ${primary}) 100%); padding: 24px 22px; color: #fff; text-shadow: 0 1px 8px rgba(0,0,0,.25);`">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px;">
                            <div>
                                <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; opacity: .9;">{{ __('Direct booking · No commission') }}</div>
                                <div style="font-size: 22px; font-weight: 700; letter-spacing: -0.015em; margin-top: 6px;">{{ $tenant->business_name }}</div>
                            </div>
                            <span :style="`background: ${accent}; color: ${contrastInk(accent)}; padding: 5px 11px; border-radius: 999px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; text-shadow: none; box-shadow: 0 4px 12px rgba(0,0,0,.15);`">{{ __('Featured') }}</span>
                        </div>
                    </div>

                    {{-- Body preview --}}
                    <div style="padding: 18px 22px; background: var(--bg-elev); display:flex; flex-direction:column; gap: 16px;">
                        {{-- Buttons --}}
                        <div style="display:flex; flex-wrap:wrap; gap: 10px; align-items:center;">
                            <button type="button" :style="`background: ${primary}; color: ${contrastInk(primary)}; border: none; padding: 0 16px; height: 40px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor:default; box-shadow: 0 4px 12px color-mix(in srgb, ${primary} 30%, transparent);`">
                                {{ __('Reserve now') }} →
                            </button>
                            <button type="button" :style="`background: transparent; color: ${secondary}; border: 1.5px solid ${secondary}; padding: 0 14px; height: 40px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor:default;`">
                                {{ __('Contact host') }}
                            </button>
                            <span :style="`background: color-mix(in srgb, ${primary} 12%, #fff); color: ${primary}; padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 600;`">● {{ __('Available') }}</span>
                            <span :style="`background: color-mix(in srgb, ${accent} 14%, #fff); color: color-mix(in srgb, ${accent} 70%, #000); padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 600;`">{{ __('Best value') }}</span>
                        </div>

                        {{-- Mini calendar preview --}}
                        <div style="display:flex; gap: 6px; align-items:center;">
                            @foreach (range(1, 7) as $d)
                                @php $state = $d === 3 ? 'in' : ($d === 5 ? 'out' : ($d >= 3 && $d <= 5 ? 'range' : 'avail')); @endphp
                                @if ($state === 'in' || $state === 'out')
                                    <div :style="`width: 38px; height: 44px; border-radius: 9px; background: linear-gradient(180deg, ${primary} 0%, color-mix(in srgb, ${primary} 80%, #000) 100%); color: ${contrastInk(primary)}; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size: 13px; font-weight: 700; box-shadow: 0 4px 10px color-mix(in srgb, ${primary} 30%, transparent);`">
                                        {{ 13 + $d }}
                                    </div>
                                @elseif ($state === 'range')
                                    <div :style="`width: 38px; height: 44px; border-radius: 9px; background: color-mix(in srgb, ${primary} 10%, #fff); color: color-mix(in srgb, ${primary} 75%, #000); display:flex; align-items:center; justify-content:center; font-size: 13px; font-weight: 600;`">
                                        {{ 13 + $d }}
                                    </div>
                                @else
                                    <div style="width: 38px; height: 44px; border-radius: 9px; background: var(--bg-elev); border: 1px solid var(--line); color: var(--ink-2); display:flex; align-items:center; justify-content:center; font-size: 13px; font-weight: 500;">
                                        {{ 13 + $d }}
                                    </div>
                                @endif
                            @endforeach
                            <div style="margin-left: 8px; font-size: 11px; color: var(--ink-3);">
                                <div>{{ __('2 nights selected') }}</div>
                                <div class="mono" :style="`color: color-mix(in srgb, ${secondary} 80%, #000); font-weight: 700; font-size: 13px;`">RM 440</div>
                            </div>
                        </div>

                        <template x-if="!isValid(primary) || !isValid(secondary) || !isValid(accent)">
                            <div style="padding: 8px 12px; background: var(--err-tint); color: var(--err); border-radius: var(--r-md); font-size: 11.5px;">
                                {{ __('One or more colors are invalid. Use 6-digit hex (e.g. #2596c6).') }}
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            @else
            {{-- Free tenant — theming is Pro-only. Show the palette they're on
                 (the platform default) plus an upsell; no editable inputs render,
                 so nothing colour-related is posted. --}}
            <div class="hauz-card" style="padding: 22px;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom: 14px; flex-wrap: wrap;">
                    <div>
                        <div class="kicker">{{ __('Brand & theme') }}</div>
                        <div style="margin-top: 4px; font-size: 13px; color: var(--ink-2); max-width: 520px;">
                            {{ __('Your booking page uses the default Tempahlah palette. Upgrade to Pro to pick your own brand colors for the dashboard and the public page guests see at') }}
                            <span class="mono" style="color: var(--ink); background: var(--bg-sunk); padding: 2px 6px; border-radius: 4px; font-size: 11.5px;">{{ str_replace(['https://','http://'], '', $tenant->publicUrl()) }}</span>.
                        </div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:var(--r-md); background:var(--pro-tint); color:var(--pro); font-size:12.5px;">
                    <x-icon name="lock" :size="14"/>
                    <span style="flex:1; min-width:0;">
                        {{ __('Custom brand colors are a Pro feature.') }}
                    </span>
                    <a href="{{ route('tenant.subscription') }}" class="btn btn-sm">{{ __('Upgrade') }} →</a>
                </div>
            </div>
            @endif

            <div style="display:flex; justify-content: flex-end; gap: 8px;">
                <button type="reset" class="btn">{{ __('Discard') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
            </div>
        </form>

        {{-- ─── Invoice & document branding ────────────────────────────
             Separate multipart form: the logo, tagline, address, terms and
             bank/QR details that print on every invoice + receipt. --}}
        @php
            $bDisk   = config('filesystems.default');
            $logoUrl = $tenant->logo_path ? \Storage::disk($bDisk)->url($tenant->logo_path) : null;
            $qrUrl   = $tenant->bank_qr_path ? \Storage::disk($bDisk)->url($tenant->bank_qr_path) : null;
            // Bank details stay editable on every plan — they tell a free tenant's
            // guest where to send the money. Only the fields that dress an invoice
            // or receipt PDF are Pro-only.
            $canIssueDocuments = \Laravel\Pennant\Feature::active('invoice_documents');
        @endphp
        <form method="POST" action="{{ route('tenant.settings.branding') }}" enctype="multipart/form-data"
              style="display:flex; flex-direction:column; gap: 18px; margin-top: 18px;">
            @csrf
            <div class="hauz-card" style="padding: 22px;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom: 6px; flex-wrap: wrap;">
                    <div>
                        <div class="kicker">{{ __('Invoice & documents') }}</div>
                        <div style="margin-top: 4px; font-size: 13px; color: var(--ink-2); max-width: 560px;">
                            @if ($canIssueDocuments)
                                {{ __('Your logo, tagline and bank details appear on every invoice and receipt you send to guests.') }}
                            @else
                                {{ __('Your bank details are sent to guests with their booking so they know where to pay.') }}
                            @endif
                        </div>
                    </div>
                    @if ($canIssueDocuments)
                        <x-btn-link :href="route('tenant.settings.invoice-preview')" target="_blank" rel="noopener" class="btn btn-sm">
                            {{ __('Preview sample') }} ↗
                        </x-btn-link>
                    @endif
                </div>

                @unless ($canIssueDocuments)
                    <div style="display:flex; align-items:center; gap:10px; margin-top:14px; padding:12px 14px; border-radius:var(--r-md); background:var(--pro-tint); color:var(--pro); font-size:12.5px;">
                        <x-icon name="lock" :size="14"/>
                        <span style="flex:1; min-width:0;">
                            {{ __('Logo, tagline, payment QR and terms print on invoices and receipts — a Pro feature.') }}
                        </span>
                        <a href="{{ route('tenant.subscription') }}" class="btn btn-sm">{{ __('Upgrade') }} →</a>
                    </div>
                @endunless

                {{-- Logo + tagline --}}
                <div style="display:grid; grid-template-columns: 150px 1fr; gap: 20px; align-items:start; margin-top: 18px;"
                     x-data="{ logoPreview: '{{ $logoUrl }}', remove: false }">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 8px;">{{ __('Logo') }}</label>
                        <div style="width: 150px; height: 150px; border-radius: var(--r-md); border: 1.5px dashed var(--line-2); background: var(--bg-sunk); display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;">
                            <template x-if="logoPreview && !remove">
                                <img :src="logoPreview" alt="logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            </template>
                            <template x-if="!logoPreview || remove">
                                <span style="font-size: 11px; color: var(--ink-3); text-align:center; padding: 8px;">{{ __('No logo yet') }}</span>
                            </template>
                        </div>
                        <label class="btn btn-sm" style="margin-top: 8px; width: 100%; text-align:center; cursor:pointer;">
                            {{ __('Choose image') }}
                            <input type="file" name="logo" accept="image/*" style="display:none;"
                                   @disabled(! $canIssueDocuments)
                                   @change="const f=$event.target.files[0]; if(f){ logoPreview=URL.createObjectURL(f); remove=false; }">
                        </label>
                        @if ($logoUrl)
                            <label style="display:flex; align-items:center; gap: 6px; font-size: 11.5px; color: var(--ink-3); margin-top: 8px; cursor:pointer;">
                                <input type="checkbox" name="remove_logo" value="1" x-model="remove"> {{ __('Remove current logo') }}
                            </label>
                        @endif
                    </div>
                    <div style="display:flex; flex-direction:column; gap: 14px;">
                        <div>
                            <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Tagline') }}</label>
                            <input class="input" type="text" name="invoice_tagline" maxlength="160"
                                   @disabled(! $canIssueDocuments)
                                   value="{{ old('invoice_tagline', $tenant->invoice_tagline) }}"
                                   placeholder="{{ __('e.g. Luas, Selesa, & Tenang') }}">
                            <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Shown in italics under your business name.') }}</div>
                        </div>
                        <div>
                            <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Business address') }}</label>
                            <input class="input" type="text" name="business_address" maxlength="255"
                                   value="{{ old('business_address', $tenant->business_address) }}"
                                   placeholder="{{ __('e.g. No. 1, Lorong Benar, 86000 Kluang, Johor') }}">
                            <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Printed in the document header. Leave blank to use the property address.') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Payment details --}}
                <div class="kicker" style="font-size: 9.5px; margin: 22px 0 10px;">{{ __('Payment details (footer)') }}</div>
                <div style="display:grid; grid-template-columns: 150px 1fr; gap: 20px; align-items:start;"
                     x-data="{ qrPreview: '{{ $qrUrl }}', removeQr: false }">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 8px;">{{ __('Payment QR') }}</label>
                        <div style="width: 150px; height: 150px; border-radius: var(--r-md); border: 1.5px dashed var(--line-2); background: var(--bg-sunk); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            <template x-if="qrPreview && !removeQr">
                                <img :src="qrPreview" alt="QR" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            </template>
                            <template x-if="!qrPreview || removeQr">
                                <span style="font-size: 11px; color: var(--ink-3); text-align:center; padding: 8px;">{{ __('e.g. DuitNow / bank QR') }}</span>
                            </template>
                        </div>
                        <label class="btn btn-sm" style="margin-top: 8px; width: 100%; text-align:center; cursor:pointer;">
                            {{ __('Choose QR image') }}
                            <input type="file" name="bank_qr" accept="image/*" style="display:none;"
                                   @disabled(! $canIssueDocuments)
                                   @change="const f=$event.target.files[0]; if(f){ qrPreview=URL.createObjectURL(f); removeQr=false; }">
                        </label>
                        @if ($qrUrl)
                            <label style="display:flex; align-items:center; gap: 6px; font-size: 11.5px; color: var(--ink-3); margin-top: 8px; cursor:pointer;">
                                <input type="checkbox" name="remove_qr" value="1" x-model="removeQr"> {{ __('Remove QR') }}
                            </label>
                        @endif
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; align-content:start;">
                        <div>
                            <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Bank name') }}</label>
                            <input class="input" type="text" name="bank_name" maxlength="120"
                                   value="{{ old('bank_name', $tenant->bank_name) }}" placeholder="{{ __('e.g. Bank Islam') }}">
                        </div>
                        <div>
                            <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Account holder') }}</label>
                            <input class="input" type="text" name="bank_account_holder" maxlength="120"
                                   value="{{ old('bank_account_holder', $tenant->bank_account_holder) }}" placeholder="{{ __('Name on the account') }}">
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Account number') }}</label>
                            <input class="input mono" type="text" name="bank_account_number" maxlength="60"
                                   value="{{ old('bank_account_number', $tenant->bank_account_number) }}" placeholder="e.g. 8830 0021 2739 32" autocomplete="off">
                        </div>
                    </div>
                </div>

                {{-- Terms --}}
                <div style="margin-top: 22px;">
                    <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Terms printed on documents') }}</label>
                    <textarea class="input" name="invoice_terms" rows="3" maxlength="2000"
                              @disabled(! $canIssueDocuments)
                              style="height:auto; resize:vertical;"
                              placeholder="{{ \App\Models\Tenant::DEFAULT_INVOICE_TERMS }}">{{ old('invoice_terms', $tenant->invoice_terms) }}</textarea>
                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Leave blank to use the default terms.') }}</div>
                </div>

                <div style="display:flex; justify-content: flex-end; gap: 8px; margin-top: 20px;">
                    {{-- Decodes, resizes and re-encodes the logo + payment QR, then puts
                         both to object storage — slow on a phone-sized upload. --}}
                    <x-btn-submit class="btn btn-primary">{{ __('Save invoice branding') }}</x-btn-submit>
                </div>
            </div>
        </form>
    </div>

    {{-- Onboarding deep-link. When the setup checklist sends a host here to set up
         payments (#payment-setup), smooth-scroll to that card and flash it so it's
         obvious where they landed. Native scroll-margin already offsets the sticky
         topbar, so this still works with JS disabled — it just isn't animated. --}}
    <style>
        @keyframes su-payment-flash {
            0%   { box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 55%, transparent); }
            100% { box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 0%, transparent); }
        }
        #payment-setup.is-flash { animation: su-payment-flash 1.8s ease-out 1; }
    </style>
    <script>
        (function () {
            if (window.location.hash !== '#payment-setup') return;
            var el = document.getElementById('payment-setup');
            if (!el) return;
            window.requestAnimationFrame(function () {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                el.classList.add('is-flash');
                setTimeout(function () { el.classList.remove('is-flash'); }, 2000);
            });
        })();
    </script>
</x-app-layout>
