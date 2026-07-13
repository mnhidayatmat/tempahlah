<x-app-layout :title="$campaign->subject" :breadcrumbs="[__('Platform'), __('Email marketing')]">
    @php
        $tone = match ($campaign->status) {
            'sent' => 'pill-ok',
            'sending', 'queued' => 'pill-primary',
            'cancelled' => '',
            default => 'pill-warn',
        };
        // Admin-authored markdown, rendered safely for the on-page preview.
        $previewHtml = \Illuminate\Support\Str::markdown(
            strtr($campaign->body_md, [
                '{name}' => 'Aisyah',
                '{business_name}' => 'Contoh Homestay',
                '{upgrade_url}' => rtrim((string) config('app.url'), '/').'/dashboard/subscription',
            ]),
            ['html_input' => 'strip', 'allow_unsafe_links' => false],
        );
    @endphp

    <div style="max-width: 860px; margin: 0 auto; display:flex; flex-direction:column; gap: 18px;">

        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Email marketing') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ $campaign->subject }}</div>
                <div style="margin-top: 8px; display:flex; align-items:center; gap: 10px; flex-wrap: wrap;">
                    <span class="pill {{ $tone }}" style="height: 20px; font-size: 11px;">{{ __(ucfirst($campaign->status)) }}</span>
                    <span style="font-size: 12.5px; color: var(--ink-3);">{{ $campaign->audienceLabel() }}</span>
                    @if ($campaign->test_sent_at)
                        <span style="font-size: 12.5px; color: var(--ink-3);">· {{ __('test sent :when', ['when' => $campaign->test_sent_at->diffForHumans()]) }}</span>
                    @endif
                </div>
            </div>
            <a href="{{ route('platform.marketing.index') }}" class="btn btn-sm">
                <x-icon name="arrow-left" :size="12"/> {{ __('All campaigns') }}
            </a>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--ok); background: var(--ok-tint);">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err);">{{ session('error') }}</div>
        @endif

        {{-- Delivery stats (once sending has started) --}}
        @unless ($campaign->isDraft())
            <div style="display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px;">
                @foreach ([
                    [__('Recipients'), $campaign->recipients_total, 'var(--ink)'],
                    [__('Sent'), $campaign->sent_count, 'var(--ok)'],
                    [__('Failed'), $campaign->failed_count, 'var(--err)'],
                    [__('Skipped'), $campaign->skipped_count, 'var(--ink-3)'],
                ] as [$label, $value, $color])
                    <div class="hauz-card" style="padding: 14px 16px;">
                        <div class="kicker" style="margin-bottom: 4px;">{{ $label }}</div>
                        <div class="mono" style="font-size: 22px; font-weight: 600; color: {{ $color }};">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            @if ($campaign->isRunning())
                <div style="font-size: 12px; color: var(--ink-3);">{{ __('Sending in the background — refresh this page for progress.') }}</div>
            @endif
        @endunless

        {{-- Actions --}}
        <div class="hauz-card" style="padding: 16px 18px; display:flex; gap: 10px; flex-wrap: wrap; align-items:center;">
            <form method="POST" action="{{ route('platform.marketing.test', $campaign) }}">
                @csrf
                <button type="submit" class="btn btn-sm">
                    <x-icon name="mail" :size="13"/> {{ __('Send test to me') }}
                </button>
            </form>

            @if ($campaign->isDraft())
                <a href="{{ route('platform.marketing.edit', $campaign) }}" class="btn btn-sm">{{ __('Edit') }}</a>

                <form method="POST" action="{{ route('platform.marketing.send', $campaign) }}"
                      onsubmit="return confirm('{{ __('Send this campaign to :n recipient(s) now? This cannot be undone.', ['n' => $audienceCount]) }}');">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm" @disabled(($audienceCount ?? 0) === 0)>
                        {{ __('Send to :n recipient(s)', ['n' => $audienceCount]) }} →
                    </button>
                </form>
            @elseif ($campaign->isRunning())
                <form method="POST" action="{{ route('platform.marketing.cancel', $campaign) }}"
                      onsubmit="return confirm('{{ __('Stop sending? Recipients already emailed keep their copy.') }}');">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="color: var(--err);">{{ __('Cancel sending') }}</button>
                </form>
            @endif

            @if ($campaign->isDraft() || $campaign->status === \App\Models\MarketingCampaign::STATUS_CANCELLED)
                <form method="POST" action="{{ route('platform.marketing.destroy', $campaign) }}" style="margin-left:auto;"
                      onsubmit="return confirm('{{ __('Delete this campaign?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm" style="color: var(--err);">{{ __('Delete') }}</button>
                </form>
            @endif
        </div>

        {{-- Preview --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 20px; border-bottom: .5px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
                <div class="kicker">{{ __('Preview (tokens filled with sample values)') }}</div>
            </div>
            <div style="padding: 24px 28px; font-size: 14px; line-height: 1.65; max-width: 620px;">
                {!! $previewHtml !!}
                <hr style="border: 0; border-top: .5px solid var(--line); margin: 18px 0 10px;">
                <div style="font-size: 11.5px; color: var(--ink-3);">
                    {{ __('You receive product updates because you host on Tempahlah.') }}
                    <span style="text-decoration: underline;">{{ __('Unsubscribe from marketing emails') }}</span>
                </div>
            </div>
        </div>

        {{-- Delivery log --}}
        @unless ($campaign->isDraft())
            <div class="hauz-card" style="padding: 0; overflow: hidden;">
                <div style="padding: 14px 20px; border-bottom: .5px solid var(--line);">
                    <div class="kicker">{{ __('Delivery log') }}</div>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                        <thead>
                            <tr style="background: var(--bg-sunk); font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-3);">
                                <th style="text-align:left; padding: 10px 18px;">{{ __('Tenant') }}</th>
                                <th style="text-align:left; padding: 10px 10px;">{{ __('Email') }}</th>
                                <th style="text-align:left; padding: 10px 10px;">{{ __('Status') }}</th>
                                <th style="text-align:left; padding: 10px 18px;">{{ __('Detail') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recipients as $r)
                                @php
                                    $rColor = match ($r->status) {
                                        'sent' => 'var(--ok)',
                                        'failed' => 'var(--err)',
                                        'skipped' => 'var(--ink-3)',
                                        default => 'var(--warn)',
                                    };
                                @endphp
                                <tr style="border-top: .5px solid var(--line);">
                                    <td style="padding: 10px 18px;">{{ $r->tenant?->business_name ?? '—' }}</td>
                                    <td style="padding: 10px 10px;" class="mono">{{ $r->email }}</td>
                                    <td style="padding: 10px 10px; color: {{ $rColor }}; font-weight: 600;">{{ __(ucfirst($r->status)) }}</td>
                                    <td style="padding: 10px 18px; color: var(--ink-3);">
                                        {{ $r->error ?: ($r->sent_at?->format('d M Y H:i') ?? '') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($recipients->hasPages())
                <div>{{ $recipients->links() }}</div>
            @endif
        @endunless
    </div>
</x-app-layout>
