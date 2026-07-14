<x-app-layout :title="__('Testimonials')" :subtitle="__('Moderate guest testimonials')" :breadcrumbs="[__('Platform')]">
    <div style="display:flex; flex-direction:column; gap: 22px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Platform admin') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Testimonials') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ __('Guest testimonials auto-publish. Hide or delete anything abusive, fake, or off-topic.') }}
                </div>
            </div>
            <a href="{{ route('tenant.dashboard') }}" class="btn btn-sm">
                <x-icon name="arrow-left" :size="12"/> {{ __('Back to my dashboard') }}
            </a>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--ok-tint); border-color: var(--ok); color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif

        {{-- Filter bar --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 18px; border-bottom: .5px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
                <div style="display:flex; gap: 2px; background: var(--bg-sunk); padding: 3px; border-radius: var(--r-md);">
                    @foreach ([['', __('All')], ['appealed', __('Appealed').' ('.$appealedTotal.')'], ['published', __('Published').' ('.$publishedTotal.')'], ['hidden', __('Hidden').' ('.$hiddenTotal.')']] as [$val, $lbl])
                        @php $on = request()->query('show', '') === $val; @endphp
                        <a href="{{ route('platform.testimonials', array_filter(['show' => $val, 'q' => request('q')])) }}"
                           class="btn btn-sm" style="border:0; background: {{ $on ? 'var(--bg-elev)' : 'transparent' }}; color: {{ $on ? 'var(--primary)' : 'var(--ink-2)' }}; font-weight: {{ $on ? '600' : '500' }};">{{ $lbl }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('platform.testimonials') }}" style="display:flex; gap:8px;">
                    <input type="hidden" name="show" value="{{ request('show') }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search text / name') }}" class="input" style="width: 220px; height: 34px;">
                </form>
            </div>

            <div style="display:flex; flex-direction:column;">
                @forelse ($reviews as $review)
                    @php $pendingAppeal = $review->appeal_status === \App\Models\Review::APPEAL_PENDING; @endphp
                    <div style="padding: 16px 18px; border-top: .5px solid var(--line); display:flex; align-items:flex-start; justify-content:space-between; gap: 16px; {{ $pendingAppeal ? 'background: var(--warn-tint);' : ($review->is_published ? '' : 'background: var(--bg-sunk);') }}">
                        <div style="min-width:0;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span style="color:#f5b301; font-size:14px; letter-spacing:1px;">{{ str_repeat('★', (int) $review->rating_overall).str_repeat('☆', 5 - (int) $review->rating_overall) }}</span>
                                <strong style="font-size:13.5px;">{{ $review->displayName() }}</strong>
                                @unless ($review->is_published)
                                    <span class="pill" style="height:18px; font-size:10px; background: var(--warn-tint); color: var(--warn);">{{ __('Hidden') }}</span>
                                @endunless
                                @if ($pendingAppeal)
                                    <span class="pill" style="height:18px; font-size:10px; background: var(--warn); color: #fff;">{{ __('Appeal to review') }}</span>
                                @elseif ($review->appeal_status === \App\Models\Review::APPEAL_APPROVED)
                                    <span class="pill" style="height:18px; font-size:10px; background: var(--ok-tint); color: var(--ok);">{{ __('Appeal approved') }}</span>
                                @elseif ($review->appeal_status === \App\Models\Review::APPEAL_REJECTED)
                                    <span class="pill" style="height:18px; font-size:10px; background: var(--bg-sunk); color: var(--ink-3);">{{ __('Appeal declined') }}</span>
                                @endif
                            </div>
                            @if (trim((string) $review->comment) !== '')
                                <p style="margin: 7px 0 0; font-size: 13px; color: var(--ink-2); line-height: 1.55;">"{{ $review->comment }}"</p>
                            @endif
                            <div class="mono" style="margin-top: 7px; font-size: 11px; color: var(--ink-3);">
                                {{ $review->tenant?->business_name }} · {{ $review->subject?->name }} · {{ $review->created_at?->timezone(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->format('M j, Y') }}
                            </div>

                            {{-- Tenant's appeal --}}
                            @if ($review->appeal_status)
                                <div style="margin-top: 9px; padding: 10px 12px; background: var(--bg-elev); border:.5px solid var(--line); border-radius: var(--r-md); font-size: 12px; color: var(--ink-2);">
                                    <div><strong>{{ __('Host appeal') }}:</strong> "{{ $review->appeal_reason }}"</div>
                                    <div class="mono" style="margin-top:3px; font-size:10.5px; color: var(--ink-3);">{{ __('Appealed') }} {{ $review->appealed_at?->timezone(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->format('M j, Y') }}</div>
                                    @if ($pendingAppeal)
                                        <form method="POST" action="{{ route('platform.testimonials.appeal.resolve', $review->id) }}" style="margin-top: 8px;">
                                            @csrf
                                            <input type="text" name="admin_note" maxlength="1000" class="input" style="height:32px; font-size:12px; width:100%; max-width:420px;" placeholder="{{ __('Optional note to the host…') }}">
                                            <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                                                <button type="submit" name="decision" value="approve" class="btn btn-primary btn-sm">{{ __('Approve — hide it') }}</button>
                                                <button type="submit" name="decision" value="reject" class="btn btn-sm">{{ __('Decline — keep visible') }}</button>
                                            </div>
                                        </form>
                                    @elseif (trim((string) $review->appeal_admin_note) !== '')
                                        <div style="margin-top:3px;"><strong>{{ __('Admin note') }}:</strong> {{ $review->appeal_admin_note }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div style="display:flex; gap: 6px; flex-shrink:0;">
                            <form method="POST" action="{{ route('platform.testimonials.toggle', $review->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm">
                                    {{ $review->is_published ? __('Hide') : __('Show') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('platform.testimonials.delete', $review->id) }}"
                                  onsubmit="return confirm('{{ addslashes(__('Delete this testimonial permanently?')) }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="color: var(--err); border-color: var(--err);">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="padding: 40px; text-align:center; color: var(--ink-3);">{{ __('No testimonials match.') }}</div>
                @endforelse
            </div>

            @if ($reviews->hasPages())
                <div style="padding: 14px 18px; border-top: .5px solid var(--line);">{{ $reviews->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
