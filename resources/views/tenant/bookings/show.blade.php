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

    <div style="display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.bookings.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Bookings') }}</a>
            <div style="display:flex; align-items:flex-end; justify-content:space-between; margin-top: 6px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div class="kicker">{{ $booking->reference }}</div>
                    <h2 class="display-2" style="margin: 4px 0 0;">{{ $booking->guest?->name ?? __('Guest') }}</h2>
                </div>
                @php
                    $waConnected = (bool) optional(optional($booking->tenant ?? null)->whatsappSession)->isConnected();
                @endphp
                <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                    <x-pill :variant="$ps['variant']" :dot="true">{{ $ps['label'] }}</x-pill>

                    @if ($waConnected && $booking->guest?->phone)
                        <form method="POST" action="{{ route('tenant.bookings.whatsapp', $booking->id) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="kind" value="{{ $isPaid ? 'checkin' : 'confirmation' }}">
                            <button type="submit" class="btn btn-sm" title="{{ __('Send via WhatsApp') }}">
                                {{ __('Send via WhatsApp') }}
                            </button>
                        </form>
                    @endif

                    @if (! $isPaid)
                        <form method="POST" action="{{ route('tenant.bookings.pay-link', $booking->id) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="type" value="{{ $booking->deposit_paid_at ? 'balance' : 'deposit' }}">
                            <button type="submit" class="btn btn-sm" title="{{ __('Generate Toyyibpay link') }}">
                                {{ $booking->deposit_paid_at ? __('Get balance link') : __('Get deposit link') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('tenant.bookings.send-reminder', $booking->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm" {{ ($booking->guest?->email || $booking->guest?->phone) ? '' : 'disabled title="No email or phone on file"' }}>
                                {{ __('Send reminder') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('tenant.bookings.mark-paid', $booking->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Mark paid') }}</button>
                        </form>
                    @endif

                    @php
                        $canCancel = ! in_array($booking->status, [
                            \App\Models\Booking::STATUS_CANCELLED,
                            \App\Models\Booking::STATUS_NO_SHOW,
                            \App\Models\Booking::STATUS_CHECKED_OUT,
                        ], true);
                    @endphp
                    @if ($canCancel)
                        <form method="POST"
                              action="{{ route('tenant.bookings.cancel', $booking->id) }}"
                              style="display:inline;"
                              onsubmit="
                                  var r = prompt('{{ addslashes(__('Cancel booking :ref? Optional: enter a reason (visible only to you).', ['ref' => $booking->reference])) }}', '');
                                  if (r === null) return false;
                                  this.querySelector('input[name=reason]').value = r;
                              ">
                            @csrf
                            <input type="hidden" name="reason" value="">
                            <button type="submit"
                                    class="btn btn-sm"
                                    style="color: var(--err); border-color: color-mix(in srgb, var(--err) 35%, transparent);"
                                    title="{{ __('Cancel this booking and free the dates') }}">
                                {{ __('Cancel booking') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">
                {{ session('status') }}
            </div>
        @endif

        @if (session('pay_link'))
            <div class="hauz-card" style="padding: 16px 18px; background: var(--bg-elev);" x-data="{ copied: false }">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Toyyibpay link') }}{{ session('pay_link_reused') ? ' · '.__('reused existing') : '' }}</div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" class="input" readonly
                           value="{{ session('pay_link') }}"
                           style="flex:1; font-family: var(--mono, monospace); font-size: 12.5px;"
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

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 18px;">
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
                                            {{ $p->created_at->format('d M Y · H:i') }}
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
                    <div style="font-size: 13px;">{{ $booking->guest?->name ?? '—' }}</div>
                    <div style="font-size: 12px; color: var(--ink-3); margin-bottom: 4px;">{{ $booking->guest?->email ?? '—' }}</div>
                    @if ($booking->guest?->phone)
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $booking->guest->phone) }}"
                           target="_blank" rel="noopener"
                           class="btn btn-sm" style="margin-top: 6px;">
                            WhatsApp →
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
