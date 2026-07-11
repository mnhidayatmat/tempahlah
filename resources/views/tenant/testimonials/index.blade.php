<x-app-layout :title="__('Testimonials')" :subtitle="__('What your guests say')">
    <div style="display:flex; flex-direction:column; gap: 20px; max-width: 900px;">

        {{-- Header --}}
        <div>
            <div class="kicker" style="color: var(--primary);">{{ __('Guest testimonials') }}</div>
            <div class="display-2" style="margin-top: 4px;">{{ __('Testimonials') }}</div>
            <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px; line-height: 1.5;">
                {{ __('Guests leave these after checkout. They publish straight to your booking page. To keep them trustworthy, they are read-only here — you can\'t edit or delete a testimonial. Contact Tempahlah support if one needs removing.') }}
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--ok-tint); border-color: var(--ok); color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
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
                <div class="hauz-card" style="padding: 18px 20px; {{ $review->is_published ? '' : 'opacity:.72;' }}">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap: 14px;">
                        <div style="min-width:0;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span style="color:#f5b301; font-size:15px; letter-spacing:1px;">{{ str_repeat('★', (int) $review->rating_overall).str_repeat('☆', 5 - (int) $review->rating_overall) }}</span>
                                <strong style="font-size:14px;">{{ $review->displayName() }}</strong>
                                @unless ($review->is_published)
                                    <span class="pill" style="height:18px; font-size:10px; background: var(--warn-tint); color: var(--warn);">{{ __('Hidden') }}</span>
                                @endunless
                            </div>
                            @if (trim((string) $review->comment) !== '')
                                <p style="margin: 8px 0 0; font-size: 13.5px; color: var(--ink-2); line-height: 1.55;">"{{ $review->comment }}"</p>
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
