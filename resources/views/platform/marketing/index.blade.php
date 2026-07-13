<x-app-layout :title="__('Email marketing')" :breadcrumbs="[__('Platform')]">
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
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
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
            @endif
        </div>

        @if ($campaigns->hasPages())
            <div>{{ $campaigns->links() }}</div>
        @endif
    </div>
</x-app-layout>
