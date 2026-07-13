<x-app-layout :title="__('Testimonials')" :subtitle="__('What your guests say')">
    <div style="display:flex; flex-direction:column; gap: 20px; max-width: 900px;">

        {{-- Header --}}
        <div>
            <div class="kicker" style="color: var(--primary);">{{ __('Guest testimonials') }}</div>
            <div class="display-2" style="margin-top: 4px;">{{ __('Testimonials') }}</div>
            <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px; line-height: 1.5;">
                {{ __('Guests leave these after checkout. They publish straight to your booking page. To keep them trustworthy, they are read-only here — you can\'t edit or delete a testimonial yourself. If one is unfair, abusive, or off-topic, you can appeal to have an admin hide it, giving a reason. The admin reviews it and decides.') }}
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--ok-tint); border-color: var(--ok); color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--err-tint); border-color: var(--err); color: var(--err); font-size: 13px;">{{ session('error') }}</div>
        @endif

        {{-- Stat strip --}}
        <div style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px;">
            @php
                $cards = [
                    [__('Average rating'), $avgRating ? number_format($avgRating, 1).' ★' : '—', __('published only'), 'var(--primary)'],
                    [__('Published'), (string) $publishedCount, __('live on your page'), 'var(--ok)'],
                    [__('Hidden'), (string) $hiddenCount, __('removed by an admin'), $hiddenCount > 0 ? 'var(--warn)' : 'var(--ink-2)'],
                ];
            @endphp
            @foreach ($cards as [$label, $value, $sub, $tone])
                <div class="hauz-card" style="padding: 16px;">
                    <div class="kicker" style="margin-bottom: 6px;">{{ $label }}</div>
                    <div style="font-size: 24px; font-weight: 700; line-height: 1; color: {{ $tone }};">{{ $value }}</div>
                    <div style="margin-top: 5px; font-size: 11px; color: var(--ink-3);">{{ $sub }}</div>
                </div>
            @endforeach
        </div>

        {{-- List --}}
        <div style="display:flex; flex-direction:column; gap: 12px;">
            @forelse ($reviews as $review)
                <div class="hauz-card" style="padding: 18px 20px; {{ $review->is_published ? '' : 'opacity:.72;' }}" x-data="{ appeal: false }">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap: 14px;">
                        <div style="min-width:0;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span style="color:#f5b301; font-size:15px; letter-spacing:1px;">{{ str_repeat('★', (int) $review->rating_overall).str_repeat('☆', 5 - (int) $review->rating_overall) }}</span>
                                <strong style="font-size:14px;">{{ $review->displayName() }}</strong>
                                @unless ($review->is_published)
                                    <span class="pill" style="height:18px; font-size:10px; background: var(--warn-tint); color: var(--warn);">{{ __('Hidden') }}</span>
                                @endunless
                                @if ($review->appeal_status === \App\Models\Review::APPEAL_PENDING)
                                    <span class="pill" style="height:18px; font-size:10px; background: var(--info-tint, var(--bg-sunk)); color: var(--info);">{{ __('Appeal pending review') }}</span>
                                @elseif ($review->appeal_status === \App\Models\Review::APPEAL_REJECTED)
                                    <span class="pill" style="height:18px; font-size:10px; background: var(--bg-sunk); color: var(--ink-3);">{{ __('Appeal declined') }}</span>
                                @endif
                            </div>
                            @if (trim((string) $review->comment) !== '')
                                <p style="margin: 8px 0 0; font-size: 13.5px; color: var(--ink-2); line-height: 1.55;">"{{ $review->comment }}"</p>
                            @endif

                            {{-- Appeal status / admin decision --}}
                            @if ($review->appeal_status)
                                <div style="margin-top: 10px; padding: 10px 12px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 12px; color: var(--ink-2);">
                                    <div><strong>{{ __('Your appeal') }}:</strong> "{{ $review->appeal_reason }}"</div>
                                    @if ($review->appeal_status === \App\Models\Review::APPEAL_APPROVED)
                                        <div style="margin-top:4px; color: var(--ok);">{{ __('Approved by admin — this testimonial has been hidden.') }}</div>
                                    @elseif ($review->appeal_status === \App\Models\Review::APPEAL_REJECTED)
                                        <div style="margin-top:4px; color: var(--ink-3);">{{ __('Declined by admin — it stays visible.') }}</div>
                                    @else
                                        <div style="margin-top:4px; color: var(--info);">{{ __('Waiting for an admin to review.') }}</div>
                                    @endif
                                    @if (trim((string) $review->appeal_admin_note) !== '')
                                        <div style="margin-top:4px;"><strong>{{ __('Admin note') }}:</strong> {{ $review->appeal_admin_note }}</div>
                                    @endif
                                </div>
                            @endif

                            {{-- Appeal action: only for a visible testimonial with no pending appeal --}}
                            @if ($review->is_published && $review->appeal_status !== \App\Models\Review::APPEAL_PENDING)
                                <div style="margin-top: 10px;">
                                    <button type="button" class="btn btn-sm" x-show="!appeal" @click="appeal = true">
                                        {{ $review->appeal_status === \App\Models\Review::APPEAL_REJECTED ? __('Appeal again') : __('Appeal to hide') }}
                                    </button>
                                    <form method="POST" action="{{ route('tenant.testimonials.appeal', $review->id) }}" x-show="appeal" x-cloak style="margin-top: 4px;">
                                        @csrf
                                        <label style="display:block; font-size: 11px; color: var(--ink-3); margin-bottom: 4px;">{{ __('Why should this be hidden? (unfair, abusive, off-topic, factually wrong…)') }}</label>
                                        <textarea name="appeal_reason" rows="3" maxlength="1000" required class="input" style="height:auto; padding:10px 12px; resize:vertical; font-size:13px;" placeholder="{{ __('Explain your reason for the admin…') }}"></textarea>
                                        <div style="display:flex; gap:8px; margin-top:8px;">
                                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Submit appeal') }}</button>
                                            <button type="button" class="btn btn-sm" @click="appeal = false">{{ __('Cancel') }}</button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                        <div style="text-align:right; flex-shrink:0; font-size: 11.5px; color: var(--ink-3);" class="mono">
                            <div>{{ $review->subject?->name }}</div>
                            <div style="margin-top:3px;">{{ $review->stayLabel() }}</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="hauz-card" style="padding: 40px 20px; text-align:center; color: var(--ink-3);">
                    <div style="font-size: 32px; margin-bottom: 8px;">☆</div>
                    <div style="font-weight:600; color: var(--ink-2);">{{ __('No testimonials yet') }}</div>
                    <div style="font-size: 12.5px; margin-top: 4px;">{{ __('Guests are asked for one automatically after they check out.') }}</div>
                </div>
            @endforelse
        </div>

        @if ($reviews->hasPages())
            <div>{{ $reviews->links() }}</div>
        @endif
    </div>
</x-app-layout>
