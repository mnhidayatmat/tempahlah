<x-app-layout :title="__('Email marketing')" :breadcrumbs="[__('Platform')]">
    <style>
        /* Desktop = data tables; phones = stacked cards (no horizontal scroll). */
        .pam-cards { display: none; }
        .pam-card {
            display: flex; flex-direction: column; gap: 7px;
            padding: 14px 16px; border-top: .5px solid var(--line);
            text-decoration: none; color: inherit;
        }
        .pam-card:first-child { border-top: 0; }
        .pam-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .pam-card-title { font-weight: 600; font-size: 14px; line-height: 1.35; color: var(--ink); }
        .pam-card-meta { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; font-size: 12px; color: var(--ink-3); }
        @media (max-width: 640px) {
            .pam-desk { display: none; }
            .pam-cards { display: block; }
        }
    </style>
    <div style="max-width: 980px; margin: 0 auto; display:flex; flex-direction:column; gap: 18px;">

        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Platform admin') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Email marketing') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 13px;">
                    {{ __('Campaigns to your hosts — upgrade pitches, product news. Every send honours unsubscribes and bounce suppression.') }}
                </div>
            </div>
            <a href="{{ route('platform.marketing.create') }}" class="btn btn-primary btn-sm">
                + {{ __('New campaign') }}
            </a>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--ok); background: var(--ok-tint);">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err);">{{ session('error') }}</div>
        @endif

        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            @if ($campaigns->isEmpty())
                <div style="padding: 40px; text-align: center; color: var(--ink-3);">
                    {{ __('No campaigns yet. Start with the prefilled free → Pro upgrade pitch.') }}
                </div>
            @else
                <div class="pam-desk" style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 640px;">
                        <thead>
                            <tr style="background: var(--bg-sunk); font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-3);">
                                <th style="text-align:left; padding: 12px 18px;">{{ __('Subject') }}</th>
                                <th style="text-align:left; padding: 12px 10px;">{{ __('Audience') }}</th>
                                <th style="text-align:left; padding: 12px 10px;">{{ __('Status') }}</th>
                                <th style="text-align:right; padding: 12px 10px;">{{ __('Delivered') }}</th>
                                <th style="text-align:left; padding: 12px 18px;">{{ __('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($campaigns as $c)
                                @php
                                    $tone = match ($c->status) {
                                        'sent' => 'pill-ok',
                                        'sending', 'queued' => 'pill-primary',
                                        'cancelled' => '',
                                        default => 'pill-warn',
                                    };
                                @endphp
                                <tr style="border-top: .5px solid var(--line);">
                                    <td style="padding: 12px 18px;">
                                        <a href="{{ route('platform.marketing.show', $c) }}" style="color: var(--ink); font-weight: 600; text-decoration: none;">
                                            {{ \Illuminate\Support\Str::limit($c->subject, 60) }}
                                        </a>
                                    </td>
                                    <td style="padding: 12px 10px; color: var(--ink-2);">{{ $c->audienceLabel() }}</td>
                                    <td style="padding: 12px 10px;">
                                        <span class="pill {{ $tone }}" style="height: 20px; font-size: 11px;">{{ __(ucfirst($c->status)) }}</span>
                                    </td>
                                    <td style="padding: 12px 10px; text-align: right;" class="mono">
                                        @if ($c->isDraft())
                                            —
                                        @else
                                            {{ $c->sent_count }}/{{ $c->recipients_total }}
                                            @if ($c->failed_count) <span style="color: var(--err);">· {{ $c->failed_count }} {{ __('failed') }}</span> @endif
                                        @endif
                                    </td>
                                    <td style="padding: 12px 18px; color: var(--ink-3);">{{ $c->created_at->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile: one card per campaign --}}
                <div class="pam-cards">
                    @foreach ($campaigns as $c)
                        @php
                            $tone = match ($c->status) {
                                'sent' => 'pill-ok',
                                'sending', 'queued' => 'pill-primary',
                                'cancelled' => '',
                                default => 'pill-warn',
                            };
                        @endphp
                        <a href="{{ route('platform.marketing.show', $c) }}" class="pam-card">
                            <div class="pam-card-top">
                                <span class="pam-card-title">{{ \Illuminate\Support\Str::limit($c->subject, 80) }}</span>
                                <span class="pill {{ $tone }}" style="height: 20px; font-size: 11px; flex-shrink: 0;">{{ __(ucfirst($c->status)) }}</span>
                            </div>
                            <div class="pam-card-meta">
                                <span>{{ $c->audienceLabel() }}</span>
                                <span>·</span>
                                <span>{{ $c->created_at->format('d M Y') }}</span>
                            </div>
                            <div class="pam-card-meta">
                                @if ($c->isDraft())
                                    <span>{{ __('Draft — not sent') }}</span>
                                @else
                                    <span class="mono" style="color: var(--ink-2);">{{ __('Delivered') }}: {{ $c->sent_count }}/{{ $c->recipients_total }}</span>
                                    @if ($c->failed_count) <span style="color: var(--err);">· {{ $c->failed_count }} {{ __('failed') }}</span> @endif
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($campaigns->hasPages())
            <div>{{ $campaigns->links() }}</div>
        @endif

        {{-- ── Onboarding series — the automated new-host drip ─────────── --}}
        <div style="margin-top: 10px; display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Automated') }}</div>
                <div style="font-family: var(--font-display); font-size: 22px; font-weight: 600; margin-top: 4px;">{{ __('Onboarding series') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 13px; max-width: 640px;">
                    {{ __('Every new host receives these automatically, by days since signup (daily at 09:30 MYT). Pro-pitch steps are skipped for hosts who already upgraded; unsubscribes and bounces are always honoured. Each host gets at most one email per day.') }}
                </div>
            </div>
            <a href="{{ route('platform.marketing.onboarding.create') }}" class="btn btn-sm">
                + {{ __('New step') }}
            </a>
        </div>

        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div class="pam-desk" style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 620px;">
                    <thead>
                        <tr style="background: var(--bg-sunk); font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-3);">
                            <th style="text-align:left; padding: 12px 18px;">{{ __('Step') }}</th>
                            <th style="text-align:left; padding: 12px 10px;">{{ __('Subject') }}</th>
                            <th style="text-align:center; padding: 12px 10px;">{{ __('Day') }}</th>
                            <th style="text-align:center; padding: 12px 10px;">{{ __('On') }}</th>
                            <th style="text-align:right; padding: 12px 10px;">{{ __('Sent') }}</th>
                            <th style="text-align:right; padding: 12px 18px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($onboardingSteps as $step)
                            <tr style="border-top: .5px solid var(--line); {{ $step->enabled ? '' : 'opacity: .55;' }}">
                                <td style="padding: 12px 18px;" class="mono">#{{ $step->step_no }}</td>
                                <td style="padding: 12px 10px;">
                                    {{ \Illuminate\Support\Str::limit($step->subject, 54) }}
                                    @if ($step->skip_if_paid)
                                        <span class="pill pill-pro" style="height: 17px; font-size: 9.5px; margin-left: 6px;">{{ __('Pro pitch') }}</span>
                                    @endif
                                </td>
                                <td style="padding: 12px 10px; text-align:center;" class="mono">+{{ $step->day_offset }}</td>
                                <td style="padding: 12px 10px; text-align:center;">
                                    {{ $step->enabled ? '✅' : '⏸' }}
                                </td>
                                <td style="padding: 12px 10px; text-align:right;" class="mono">
                                    {{ $step->sent_total }}
                                    @if ($step->failed_total) <span style="color: var(--err);">· {{ $step->failed_total }} ✗</span> @endif
                                </td>
                                <td style="padding: 12px 18px; text-align:right;">
                                    <a href="{{ route('platform.marketing.onboarding.edit', $step) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile: one card per onboarding step (tap to edit) --}}
            <div class="pam-cards">
                @foreach ($onboardingSteps as $step)
                    <a href="{{ route('platform.marketing.onboarding.edit', $step) }}" class="pam-card" style="{{ $step->enabled ? '' : 'opacity: .55;' }}">
                        <div class="pam-card-top">
                            <span class="pam-card-title"><span class="mono" style="color: var(--ink-3);">#{{ $step->step_no }}</span> &nbsp;{{ \Illuminate\Support\Str::limit($step->subject, 80) }}</span>
                            <span style="flex-shrink: 0;">{{ $step->enabled ? '✅' : '⏸' }}</span>
                        </div>
                        <div class="pam-card-meta">
                            @if ($step->skip_if_paid)
                                <span class="pill pill-pro" style="height: 17px; font-size: 9.5px;">{{ __('Pro pitch') }}</span>
                            @endif
                            <span>{{ __('Day') }} +{{ $step->day_offset }}</span>
                            <span>·</span>
                            <span class="mono">{{ __('Sent') }} {{ $step->sent_total }}@if ($step->failed_total) · {{ $step->failed_total }} ✗ @endif</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
