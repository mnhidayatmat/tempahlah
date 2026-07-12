<x-app-layout :title="__('Reports')">
    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div>
            <div class="kicker">{{ __('Performance') }}</div>
            <div class="display-2" style="margin-top: 4px;">{{ __('Reports') }}</div>
            <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                {{ __('Revenue, occupancy, ADR and channel-mix analytics.') }}
            </div>
        </div>

        {{-- Pro gate --}}
        <x-pro-lock
            feature="reports"
            :title="__('Reports are a Pro feature')"
            :reason="__('Track revenue, occupancy, ADR and RevPAR over the trailing 12 months, break earnings down by property and booking channel, and export it all as a PDF. Upgrade to Pro to unlock your analytics dashboard.')"
            :cta="__('Upgrade — RM49/mo')" />

    </div>
</x-app-layout>
