@php
    $session = $this->session;
    $status = $session->status;
    $isConnected = $status === \App\Models\WhatsappSession::STATUS_CONNECTED;
    $isQr = $status === \App\Models\WhatsappSession::STATUS_QR_PENDING;
    $isErrored = in_array($status, [
        \App\Models\WhatsappSession::STATUS_BANNED,
        \App\Models\WhatsappSession::STATUS_ERROR,
        \App\Models\WhatsappSession::STATUS_EXPIRED,
    ]);
@endphp

<div wire:poll.3s>
    @if ($flash)
        <div class="hauz-card"
             style="padding: 10px 14px; margin-bottom: 16px; font-size: 13px;
                    border-color: var({{ $flashKind === 'err' ? '--err' : '--ok' }});
                    background: var({{ $flashKind === 'err' ? '--err-tint' : '--ok-tint' }});
                    color: var({{ $flashKind === 'err' ? '--err' : '--ok' }});">
            {{ $flash }}
        </div>
    @endif

    @unless ($this->sidecarReachable)
        <div class="hauz-card"
             style="padding: 12px 16px; margin-bottom: 16px; border-color: var(--err);
                    background: var(--err-tint); color: var(--err); font-size: 13px;">
            <strong>{{ __('WhatsApp sidecar is offline.') }}</strong>
            <div style="margin-top: 4px; color: var(--ink-2);">
                {{ __('The WhatsApp service is not running on this server. Contact your administrator.') }}
            </div>
        </div>
    @endunless

    {{-- ── DISCONNECTED ─────────────────────────────────────────────── --}}
    @if ($status === \App\Models\WhatsappSession::STATUS_DISCONNECTED)
        <div class="hauz-card" style="padding: 32px 28px; text-align: center;">
            <div style="font-size: 44px; line-height: 1;">📱</div>
            <h3 class="display-3" style="margin: 14px 0 6px;">{{ __('Connect your WhatsApp') }}</h3>
            <p style="margin: 0 auto; max-width: 380px; color: var(--ink-3); font-size: 14px;">
                {{ __('We will show a QR code. Scan it from the WhatsApp app on the phone you want to use for sending booking messages.') }}
            </p>
            <div style="margin-top: 22px;">
                <button class="btn btn-primary" wire:click="start" @disabled(! $this->sidecarReachable)>
                    {{ __('Show QR code') }}
                </button>
            </div>
            <div style="margin-top: 18px; font-size: 11.5px; color: var(--ink-3);">
                {{ __('Tip: use a dedicated business number that is not your personal WhatsApp.') }}
            </div>
        </div>

    {{-- ── CONNECTING ───────────────────────────────────────────────── --}}
    @elseif ($status === \App\Models\WhatsappSession::STATUS_CONNECTING)
        <div class="hauz-card" style="padding: 28px; text-align: center;">
            <div style="font-size: 13px; color: var(--ink-3);">{{ __('Connecting…') }}</div>
            <div style="margin-top: 8px; font-size: 11.5px; color: var(--ink-3);">{{ __('Waiting for the QR code from the sidecar.') }}</div>
        </div>

    {{-- ── QR PENDING ───────────────────────────────────────────────── --}}
    @elseif ($isQr)
        <div class="hauz-card" style="padding: 28px; text-align: center;">
            <div style="display: grid; grid-template-columns: 1fr; gap: 18px; justify-items: center;">
                <div>
                    <div class="kicker">{{ __('Scan to connect') }}</div>
                    <h3 class="display-3" style="margin: 4px 0 0;">{{ __('Open WhatsApp on your phone') }}</h3>
                </div>

                @if ($session->qr_payload)
                    <img src="{{ $session->qr_payload }}" alt="WhatsApp QR"
                         style="width: 280px; height: 280px; border-radius: 16px;
                                border: .5px solid var(--line); padding: 12px; background: var(--bg-elev);">
                @else
                    <div style="width: 280px; height: 280px; border-radius: 16px;
                                border: 1px dashed var(--line); display:flex; align-items:center; justify-content:center; color: var(--ink-3); font-size: 12px;">
                        {{ __('Generating QR…') }}
                    </div>
                @endif

                <ol style="text-align: left; max-width: 340px; padding-left: 18px; margin: 0;
                           font-size: 13px; color: var(--ink-2); line-height: 1.65;">
                    <li>{{ __('Open WhatsApp on your phone.') }}</li>
                    <li>{{ __('Tap Menu → Linked Devices → Link a device.') }}</li>
                    <li>{{ __('Point your phone at this screen to scan.') }}</li>
                </ol>

                <div style="display: flex; gap: 8px;">
                    <button class="btn" wire:click="refresh">{{ __('Refresh') }}</button>
                    <button class="btn btn-ghost" wire:click="disconnect" style="color: var(--err);">{{ __('Cancel') }}</button>
                </div>

                @if ($session->qr_generated_at)
                    <div style="font-size: 11px; color: var(--ink-3);">
                        {{ __('QR generated :ago.', ['ago' => $session->qr_generated_at->diffForHumans()]) }}
                    </div>
                @endif
            </div>
        </div>

    {{-- ── CONNECTED ─────────────────────────────────────────────────── --}}
    @elseif ($isConnected)
        <div class="hauz-card" style="padding: 22px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
                <div>
                    <div class="kicker">{{ __('Connected as') }}</div>
                    <h3 class="display-3" style="margin: 4px 0 0;">
                        {{ $session->push_name ?: __('WhatsApp account') }}
                    </h3>
                    <div style="margin-top: 4px; font-family: var(--mono, monospace); color: var(--ink-2); font-size: 13px;">
                        {{ $session->phone_e164 ?? '—' }}
                    </div>
                    @if ($session->connected_at)
                        <div style="margin-top: 6px; font-size: 11.5px; color: var(--ink-3);">
                            {{ __('Connected :ago', ['ago' => $session->connected_at->diffForHumans()]) }}
                            @if ($session->daily_sent_count > 0)
                                · {{ __(':n sent today', ['n' => $session->daily_sent_count]) }}
                            @endif
                        </div>
                    @endif
                </div>
                <x-pill variant="ok" :dot="true">{{ __('Connected') }}</x-pill>
            </div>

            <div style="margin-top: 18px; border-top: .5px solid var(--line); padding-top: 16px;">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Send a test message') }}</div>
                <div style="display: grid; grid-template-columns: 1.4fr 1fr auto; gap: 8px;">
                    <input class="input" type="text" wire:model="testPhone" placeholder="+60123456789">
                    <input class="input" type="text" wire:model="testName" placeholder="{{ __('Name (optional)') }}">
                    <button class="btn btn-primary" wire:click="sendTest">{{ __('Send test') }}</button>
                </div>
                @error('testPhone') <div style="font-size: 11.5px; color: var(--err); margin-top: 4px;">{{ $message }}</div> @enderror
                <div style="margin-top: 6px; font-size: 11px; color: var(--ink-3);">
                    {{ __('Only numbers that are already in your bookings can receive messages.') }}
                </div>
            </div>

            <div style="margin-top: 16px; text-align: right;">
                <button class="btn btn-ghost" wire:click="disconnect"
                        wire:confirm="{{ __('Disconnect WhatsApp? You will need to scan the QR again to reconnect.') }}"
                        style="color: var(--err); font-size: 12px;">
                    {{ __('Disconnect') }}
                </button>
            </div>
        </div>

    {{-- ── ERROR / BANNED / EXPIRED ─────────────────────────────────── --}}
    @elseif ($isErrored)
        <div class="hauz-card" style="padding: 22px; border-color: var(--err); background: var(--err-tint);">
            <div class="kicker" style="color: var(--err);">{{ __('Disconnected') }}</div>
            <h3 class="display-3" style="margin: 4px 0 0; color: var(--err);">
                @if ($status === \App\Models\WhatsappSession::STATUS_BANNED)
                    {{ __('WhatsApp closed the session') }}
                @else
                    {{ __('Connection lost') }}
                @endif
            </h3>
            @if ($session->last_error)
                <div style="margin-top: 6px; font-size: 12.5px; color: var(--ink-2);">
                    {{ $session->last_error }}
                </div>
            @endif
            <div style="margin-top: 16px;">
                <button class="btn btn-primary" wire:click="start" @disabled(! $this->sidecarReachable)>
                    {{ __('Reconnect') }}
                </button>
            </div>
            @if ($status === \App\Models\WhatsappSession::STATUS_BANNED)
                <div style="margin-top: 14px; font-size: 11.5px; color: var(--ink-3);">
                    {{ __('If WhatsApp keeps closing the session, your number may be flagged. Wait 24 hours before retrying, and avoid sending to people who have not booked with you.') }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── RECENT SENDS ─────────────────────────────────────────────── --}}
    @if ($this->recentMessages->isNotEmpty())
        <div class="hauz-card" style="padding: 18px 22px; margin-top: 20px;">
            <div class="kicker" style="margin-bottom: 12px;">{{ __('Recent sends') }}</div>
            <table style="width: 100%; font-size: 12.5px; border-collapse: collapse;">
                <thead style="color: var(--ink-3); text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.05em;">
                    <tr>
                        <th style="text-align: left; padding-bottom: 6px;">{{ __('When') }}</th>
                        <th style="text-align: left; padding-bottom: 6px;">{{ __('To') }}</th>
                        <th style="text-align: left; padding-bottom: 6px;">{{ __('Kind') }}</th>
                        <th style="text-align: right; padding-bottom: 6px;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($this->recentMessages as $m)
                    <tr style="border-top: .5px solid var(--line);">
                        <td style="padding: 8px 0; color: var(--ink-2);">{{ $m->created_at->diffForHumans() }}</td>
                        <td style="padding: 8px 0; font-family: var(--mono, monospace);">{{ $m->recipient_phone }}</td>
                        <td style="padding: 8px 0; color: var(--ink-2);">{{ $m->kind }}</td>
                        <td style="padding: 8px 0; text-align: right;">
                            @php
                                $variant = match ($m->status) {
                                    \App\Models\WhatsappMessage::STATUS_SENT,
                                    \App\Models\WhatsappMessage::STATUS_DELIVERED,
                                    \App\Models\WhatsappMessage::STATUS_READ => 'ok',
                                    \App\Models\WhatsappMessage::STATUS_FAILED,
                                    \App\Models\WhatsappMessage::STATUS_BLOCKED => 'err',
                                    \App\Models\WhatsappMessage::STATUS_RATE_LIMITED => 'warn',
                                    default => 'default',
                                };
                            @endphp
                            <x-pill :variant="$variant">{{ $m->status }}</x-pill>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
