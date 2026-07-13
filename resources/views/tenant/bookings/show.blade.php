<x-app-layout :title="__('Booking :ref', ['ref' => $booking->reference])">
    @php
        $totalPaid = (float) $booking->payments->where('status', 'succeeded')->sum('amount');
        $remaining = max(0, (float) $booking->total_amount - $totalPaid);
        $isPaid = $remaining <= 0;
        $ps = $isPaid ? ['variant' => 'ok', 'label' => __('Paid')]
            : ($booking->deposit_paid_at ? ['variant' => 'warn', 'label' => __('Deposit')]
            : ['variant' => 'err', 'label' => __('Unpaid')]);
        $nights = (int) $booking->nights ?: $booking->check_in->diffInDays($booking->check_out);
        $totalGuests = (int) $booking->adults + (int) $booking->children;
        $checkInTime = optional($booking->property)->check_in_time ?? '15:00';
        $checkOutTime = optional($booking->property)->check_out_time ?? '11:00';
    @endphp

    {{-- Page-scoped styling (no shared CSS touched). Every booking action lives
         in one compact "Edit" dropdown menu instead of a crowded button row, and
         on phones the 2fr/1fr detail grid collapses to a single readable column
         so nothing overflows sideways. --}}
    <style>
        [x-cloak] { display:none !important; }
        .bk-menu { box-shadow: 0 12px 32px -8px rgba(20,20,30,.20), 0 2px 6px rgba(20,20,30,.08); }
        .bk-menu form { margin:0; }
        .bk-menu-item {
            display:flex; align-items:center; gap:10px; width:100%;
            padding:9px 10px; border:0; background:transparent; border-radius:8px;
            font-size:13px; color:var(--ink); text-align:left; cursor:pointer;
            text-decoration:none; font-family:inherit; line-height:1.25;
        }
        .bk-menu-item:hover { background: var(--bg-sunk); }
        .bk-menu-item[disabled] { opacity:.45; cursor:not-allowed; }
        .bk-menu-item[disabled]:hover { background:transparent; }
        .bk-menu-item--primary { color: var(--primary); font-weight:600; }
        .bk-menu-item--danger  { color: var(--err); }
        .bk-menu-sep { height:1px; background:var(--line); margin:5px 4px; }

        /* Invoice & receipt document styles live in the shared
           booking.documents component (rendered here + in the calendar). */

        @media (max-width: 768px) {
            .bk-root { gap:14px !important; }
            .bk-head { align-items:flex-start; }

            /* Detail grid → single column (kills the cramped 2fr/1fr that
               squeezed the Total card and could overflow sideways). */
            .bk-grid { grid-template-columns:1fr !important; gap:12px !important; }

            .bk-paylink-row { flex-wrap:wrap; }
        }
    </style>

    <div class="bk-root" style="display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.bookings.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Bookings') }}</a>
            <div class="bk-head" style="display:flex; align-items:flex-end; justify-content:space-between; margin-top: 6px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div class="kicker">{{ $booking->reference }}</div>
                    <h2 class="display-2" style="margin: 4px 0 0;">{{ $booking->guestName() ?? __('Guest') }}</h2>
                </div>
                <x-pill :variant="$ps['variant']" :dot="true">{{ $ps['label'] }}</x-pill>
            </div>
            @php
                $waConnected = (bool) optional(optional($booking->tenant ?? null)->whatsappSession)->isConnected();
            @endphp
            @php
                $canCancel = ! in_array($booking->status, [
                    \App\Models\Booking::STATUS_CANCELLED,
                    \App\Models\Booking::STATUS_NO_SHOW,
                    \App\Models\Booking::STATUS_CHECKED_OUT,
                ], true);
                $canCheckOut = in_array($booking->status, [
                    \App\Models\Booking::STATUS_CONFIRMED,
                    \App\Models\Booking::STATUS_CHECKED_IN,
                ], true);
            @endphp

            {{-- One compact "Edit" control opens a menu with every booking action.
                 Replaces the old crowded row of 6–8 inline buttons. --}}
            <div class="bk-actions" x-data="{ open: false }" style="position:relative; margin-top: 12px;">
                <button type="button" class="btn btn-sm" @click="open = ! open"
                        :aria-expanded="open ? 'true' : 'false'"
                        style="display:inline-flex; align-items:center; gap:7px;">
                    <x-icon name="cog" :size="13"/> {{ __('Edit') }}
                    <x-icon name="arrow-down" :size="11"/>
                </button>

                <div x-show="open" x-cloak x-transition
                     @click.outside="open = false"
                     @keydown.escape.window="open = false"
                     class="hauz-card bk-menu"
                     style="position:absolute; left:0; top:calc(100% + 6px); z-index:60; width:240px; padding:6px;">

                    {{-- Edit booking details (the full edit form) --}}
                    <a href="{{ route('tenant.bookings.edit', $booking->id) }}" class="bk-menu-item">
                        <x-icon name="cog" :size="14"/> <span>{{ __('Edit details') }}</span>
                    </a>

                    {{-- Send via WhatsApp --}}
                    @if ($waConnected && $booking->guest?->phone)
                        <form method="POST" action="{{ route('tenant.bookings.whatsapp', $booking->id) }}">
                            @csrf
                            <input type="hidden" name="kind" value="{{ $isPaid ? 'checkin' : 'confirmation' }}">
                            <button type="submit" class="bk-menu-item">
                                <x-icon name="message" :size="14"/> <span>{{ __('Send via WhatsApp') }}</span>
                            </button>
                        </form>

                        {{-- Share homestay location/directions via WhatsApp --}}
                        <form method="POST" action="{{ route('tenant.bookings.whatsapp', $booking->id) }}">
                            @csrf
                            <input type="hidden" name="kind" value="location">
                            <button type="submit" class="bk-menu-item">
                                <x-icon name="pin" :size="14"/> <span>{{ __('Share location') }}</span>
                            </button>
                        </form>
                    @endif

                    @if (! $isPaid)
                        {{-- Send reminder --}}
                        <form method="POST" action="{{ route('tenant.bookings.send-reminder', $booking->id) }}">
                            @csrf
                            <button type="submit" class="bk-menu-item"
                                    {{ ($booking->guest?->email || $booking->guest?->phone) ? '' : 'disabled title="No email or phone on file"' }}>
                                <x-icon name="bell" :size="14"/> <span>{{ __('Send reminder') }}</span>
                            </button>
                        </form>

                        {{-- Manual payment — the guest paid the host directly
                             (cash / bank transfer). "Booking fee" confirms the
                             booking; "fully paid" settles the whole balance. --}}
                        @unless ($booking->deposit_paid_at)
                            <form method="POST" action="{{ route('tenant.bookings.mark-paid', $booking->id) }}">
                                @csrf
                                <input type="hidden" name="kind" value="booking_fee">
                                <x-btn-submit class="bk-menu-item">
                                    <x-icon name="check" :size="14"/> <span>{{ __('Mark booking fee paid') }}</span>
                                </x-btn-submit>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('tenant.bookings.mark-paid', $booking->id) }}">
                            @csrf
                            <input type="hidden" name="kind" value="full">
                            <x-btn-submit class="bk-menu-item bk-menu-item--primary">
                                <x-icon name="check" :size="14"/> <span>{{ __('Mark fully paid') }}</span>
                            </x-btn-submit>
                        </form>
                    @endif

                    {{-- Check out guest --}}
                    @if ($canCheckOut)
                        <form method="POST"
                              action="{{ route('tenant.bookings.check-out', $booking->id) }}"
                              onsubmit="return confirm('{{ addslashes(__('Mark guest as checked out? This stamps the checkout time and prepares a refund for the deposit.')) }}');">
                            @csrf
                            <button type="submit" class="bk-menu-item"
                                    title="{{ __('Stamp checkout time + auto-prepare deposit refund') }}">
                                <x-icon name="arrow-right" :size="14"/> <span>{{ __('Check out guest') }}</span>
                            </button>
                        </form>
                    @endif

                    {{-- Request testimonial — only after checkout, until one is left --}}
                    @if ($booking->status === \App\Models\Booking::STATUS_CHECKED_OUT && ! $booking->review)
                        <form method="POST" action="{{ route('tenant.bookings.request-review', $booking->id) }}">
                            @csrf
                            <button type="submit" class="bk-menu-item"
                                    title="{{ __('Send the guest a link to leave a testimonial') }}">
                                <x-icon name="star" :size="14"/>
                                <span>{{ $booking->review_requested_at ? __('Re-send testimonial request') : __('Request testimonial') }}</span>
                            </button>
                        </form>
                    @endif

                    @if ($canCancel)
                        <div class="bk-menu-sep"></div>
                        {{-- Cancel booking --}}
                        <form method="POST"
                              action="{{ route('tenant.bookings.cancel', $booking->id) }}"
                              onsubmit="
                                  var r = prompt('{{ addslashes(__('Cancel booking :ref? Optional: enter a reason (visible only to you).', ['ref' => $booking->reference])) }}', '');
                                  if (r === null) return false;
                                  this.querySelector('input[name=reason]').value = r;
                              ">
                            @csrf
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="bk-menu-item bk-menu-item--danger"
                                    title="{{ __('Cancel this booking and free the dates') }}">
                                <x-icon name="x" :size="14"/> <span>{{ __('Cancel booking') }}</span>
                            </button>
                        </form>
                    @else
                        <div class="bk-menu-sep"></div>
                    @endif

                    {{-- Hard delete — strong typed confirm so a misclick can't wipe a
                         real booking; the server also refuses if audit rows exist. --}}
                    <form method="POST"
                          action="{{ route('tenant.bookings.destroy', $booking->id) }}"
                          onsubmit="
                              var typed = prompt('{{ addslashes(__('PERMANENT DELETE — type the booking reference :ref to confirm. This wipes the booking, its payments, invoices and tasks. Use \'Cancel\' instead if it\'s a real booking.', ['ref' => $booking->reference])) }}', '');
                              if (typed !== '{{ $booking->reference }}') {
                                  if (typed !== null) alert('{{ addslashes(__('Reference did not match — delete aborted.')) }}');
                                  return false;
                              }
                          ">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bk-menu-item bk-menu-item--danger"
                                title="{{ __('Permanently delete (cannot be undone)') }}">
                            <x-icon name="x" :size="14"/> <span>{{ __('Delete booking') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                {{ session('error') }}
            </div>
        @endif

        {{-- Invoice & receipt — generate the PDF, then email or WhatsApp it to
             the guest (shared component, also used in the calendar day panel). --}}
        <div class="hauz-card" style="padding: 18px;">
            <div class="kicker" style="margin-bottom: 6px;">{{ __('Invoice & receipt') }}</div>
            <div style="font-size:12.5px; color:var(--ink-3); margin-bottom: 6px;">
                {{ __('Generate the PDF and send it to the guest by email or WhatsApp.') }}
            </div>
            <x-booking.documents :booking="$booking"/>
        </div>

        @if (session('pay_link'))
            <div class="hauz-card" style="padding: 16px 18px; background: var(--bg-elev);" x-data="{ copied: false }">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Toyyibpay link') }}{{ session('pay_link_reused') ? ' · '.__('reused existing') : '' }}</div>
                <div class="bk-paylink-row" style="display:flex; gap:8px; align-items:center;">
                    <input type="text" class="input" readonly
                           value="{{ session('pay_link') }}"
                           style="flex:1; min-width:0; font-family: var(--mono, monospace); font-size: 12.5px;"
                           x-ref="link" @click="$refs.link.select()">
                    <button type="button" class="btn btn-sm"
                            @click="navigator.clipboard.writeText($refs.link.value); copied=true; setTimeout(()=>copied=false, 1500)">
                        <span x-show="!copied">{{ __('Copy') }}</span>
                        <span x-show="copied" style="color: var(--ok);">{{ __('Copied ✓') }}</span>
                    </button>
                    <a href="{{ session('pay_link') }}" target="_blank" rel="noopener" class="btn btn-sm">{{ __('Open ↗') }}</a>
                </div>
                <div style="font-size: 11.5px; color: var(--ink-3); margin-top: 6px;">
                    {{ __('Share this link with the guest. The booking auto-updates when Toyyibpay confirms payment.') }}
                </div>
            </div>
        @endif

        <div class="bk-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap: 18px;">
            <div style="display:flex; flex-direction:column; gap: 14px;">
                <div class="hauz-card" style="padding: 18px;">
                    <div class="kicker" style="margin-bottom: 10px;">{{ __('Stay') }}</div>
                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 14px;">
                        <div>
                            <div style="font-size: 11px; color: var(--ink-3); margin-bottom: 4px;">{{ __('Check-in') }}</div>
                            <div style="font-family: var(--font-display); font-size: 20px;">{{ $booking->check_in->format('d M') }}</div>
                            <div class="mono" style="font-size: 11.5px; color: var(--ink-3);">{{ $booking->check_in->format('Y') }} · {{ $checkInTime }}</div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--ink-3); margin-bottom: 4px;">{{ __('Check-out') }}</div>
                            <div style="font-family: var(--font-display); font-size: 20px;">{{ $booking->check_out->format('d M') }}</div>
                            <div class="mono" style="font-size: 11.5px; color: var(--ink-3);">{{ $booking->check_out->format('Y') }} · {{ $checkOutTime }}</div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--ink-3); margin-bottom: 4px;">{{ __('Nights') }}</div>
                            <div style="font-family: var(--font-display); font-size: 20px;">{{ $nights }}</div>
                            <div class="mono" style="font-size: 11.5px; color: var(--ink-3);">{{ $totalGuests }} {{ trans_choice('{1} guest|[2,*] guests', $totalGuests) }}</div>
                        </div>
                    </div>
                </div>

                <div class="hauz-card" style="padding: 18px;">
                    <div class="kicker" style="margin-bottom: 10px;">{{ __('Property') }}</div>
                    <div style="display:flex; gap: 12px; align-items:center;">
                        @if ($booking->property)
                            <x-property-visual :property="$booking->property" :size="48"/>
                            <div>
                                <div style="font-weight: 500;">{{ $booking->property->name }}</div>
                                <div style="font-size: 12px; color: var(--ink-3);">
                                    {{ $booking->property->city ?? '—' }}
                                    @if ($booking->room) · {{ __('Room: :room', ['room' => $booking->room->name]) }} @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($booking->payments->isNotEmpty())
                    <div class="hauz-card" style="padding: 18px;">
                        <div class="kicker" style="margin-bottom: 10px;">{{ __('Payment history') }}</div>
                        <div style="display:flex; flex-direction:column; gap: 8px; font-size: 13px;">
                            @foreach ($booking->payments->sortByDesc('created_at') as $p)
                                <div style="display:flex; justify-content:space-between; align-items:center; padding: 8px 10px; background: var(--bg-sunk); border-radius: var(--r-md);">
                                    <div>
                                        <div style="font-weight: 500;">{{ ucfirst($p->type) }} · {{ ucfirst($p->method) }}</div>
                                        <div style="font-size: 11.5px; color: var(--ink-3);" class="mono">
                                            {{ $p->created_at->timezone(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->format('d M Y · H:i') }}
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div class="mono" style="font-weight: 600;">RM {{ number_format((float) $p->amount, 2) }}</div>
                                        @php $cls = $p->status === 'succeeded' ? 'pill-ok' : ($p->status === 'pending' ? 'pill-warn' : 'pill'); @endphp
                                        <span class="pill {{ $cls }}" style="height: 16px; font-size: 9.5px;">{{ ucfirst($p->status) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @include('tenant.bookings.partials.refunds', ['booking' => $booking])
            </div>

            <div style="display:flex; flex-direction:column; gap: 14px;">
                <div class="hauz-card" style="padding: 16px;">
                    <div class="kicker" style="margin-bottom: 8px;">{{ __('Total') }}</div>
                    <div style="display:flex; align-items:baseline; gap: 4px; margin-bottom: 12px;">
                        <span class="mono" style="font-size: 13px; color: var(--ink-3);">RM</span>
                        <span style="font-family: var(--font-display); font-size: 32px; line-height: 1;">{{ number_format((float) $booking->total_amount, 0) }}</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap: 5px; font-size: 12.5px;">
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Base') }}</span><span class="mono">RM{{ number_format((float) $booking->base_amount, 2) }}</span></div>
                        @if ((float) $booking->sst_amount > 0)
                            <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('SST') }}</span><span class="mono">RM{{ number_format((float) $booking->sst_amount, 2) }}</span></div>
                        @endif
                        @if ((float) $booking->tourism_tax_amount > 0)
                            <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Tourism tax') }}</span><span class="mono">RM{{ number_format((float) $booking->tourism_tax_amount, 2) }}</span></div>
                        @endif
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Deposit') }}</span><span class="mono">RM{{ number_format((float) $booking->deposit_amount, 2) }}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Paid') }}</span><span class="mono" style="color: var(--ok);">RM{{ number_format($totalPaid, 2) }}</span></div>
                        @if (! $isPaid)
                            <div style="display:flex; justify-content:space-between; padding-top: 6px; border-top: .5px solid var(--line);"><span style="font-weight: 500;">{{ __('Outstanding') }}</span><span class="mono" style="font-weight: 600; color: var(--err);">RM{{ number_format($remaining, 2) }}</span></div>
                        @endif
                        <div style="display:flex; justify-content:space-between;"><span style="color: var(--ink-3);">{{ __('Channel') }}</span><span>{{ ucfirst((string) ($booking->channel ?? 'direct')) }}</span></div>
                    </div>
                </div>

                <div class="hauz-card" style="padding: 16px;">
                    <div class="kicker" style="margin-bottom: 8px;">{{ __('Contact') }}</div>
                    <div style="font-size: 13px;">{{ $booking->guestName() ?? '—' }}</div>
                    <div style="font-size: 12px; color: var(--ink-3); margin-bottom: 4px;">{{ $booking->guestEmail() ?? '—' }}</div>
                    @if ($booking->guestPhone())
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $booking->guestPhone()) }}"
                           target="_blank" rel="noopener"
                           class="btn btn-sm" style="margin-top: 6px;">
                            WhatsApp →
                        </a>
                    @endif

                    @php
                        $referralLabels = [
                            'instagram' => 'Instagram',
                            'facebook'  => 'Facebook / WhatsApp',
                            'friend'    => __('Friend or family'),
                            'google'    => __('Google search'),
                            'repeat'    => __('Repeat guest'),
                            'other'     => __('Other'),
                        ];
                        $referral = data_get($booking->meta, 'referral_source');
                    @endphp
                    @if ($referral && isset($referralLabels[$referral]))
                        <div style="margin-top: 12px; padding-top: 12px; border-top: .5px solid var(--line);">
                            <div style="font-size: 11px; color: var(--ink-3); margin-bottom: 3px;">{{ __('How they found us') }}</div>
                            <div style="font-size: 13px;">{{ $referralLabels[$referral] }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
