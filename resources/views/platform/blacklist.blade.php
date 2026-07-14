<x-app-layout :title="__('Guest blacklist')" :subtitle="__('Review guest reports')" :breadcrumbs="[__('Platform')]">
    <div style="display:flex; flex-direction:column; gap: 22px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Platform admin') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Guest blacklist') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px; max-width: 620px;">
                    {{ __('Homestays report problem guests. Verifying a report flags that guest for every homestay — they see an alert before accepting a booking. Only verify with clear evidence.') }}
                </div>
            </div>
            <a href="{{ route('tenant.dashboard') }}" class="btn btn-sm">
                <x-icon name="arrow-left" :size="12"/> {{ __('Back to my dashboard') }}
            </a>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--ok-tint); border-color: var(--ok); color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif

        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            {{-- Filter bar --}}
            <div style="padding: 14px 18px; border-bottom: .5px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
                <div style="display:flex; gap: 2px; background: var(--bg-sunk); padding: 3px; border-radius: var(--r-md);">
                    @foreach ([
                        [\App\Models\GuestBlacklistEntry::STATUS_PENDING, __('Pending').' ('.$pendingTotal.')'],
                        [\App\Models\GuestBlacklistEntry::STATUS_APPROVED, __('Verified').' ('.$approvedTotal.')'],
                        [\App\Models\GuestBlacklistEntry::STATUS_REJECTED, __('Rejected').' ('.$rejectedTotal.')'],
                    ] as [$val, $lbl])
                        @php $on = $status === $val; @endphp
                        <a href="{{ route('platform.blacklist', array_filter(['status' => $val, 'q' => request('q')])) }}"
                           class="btn btn-sm" style="border:0; background: {{ $on ? 'var(--bg-elev)' : 'transparent' }}; color: {{ $on ? 'var(--primary)' : 'var(--ink-2)' }}; font-weight: {{ $on ? '600' : '500' }};">{{ $lbl }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('platform.blacklist') }}" style="display:flex; gap:8px;">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name / phone / email') }}" class="input" style="width: 220px; height: 34px;">
                </form>
            </div>

            <div style="display:flex; flex-direction:column;">
                @forelse ($entries as $entry)
                    <div style="padding: 16px 18px; border-top: .5px solid var(--line); display:flex; align-items:flex-start; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
                        <div style="min-width: 260px; flex: 1;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <x-pill :variant="$entry->severityVariant()" :dot="true">{{ __($entry->severityLabel()) }}</x-pill>
                                <strong style="font-size:13.5px;">{{ $entry->displayName() }}</strong>
                                <span class="pill" style="height:18px; font-size:10.5px;">{{ __($entry->reasonLabel()) }}</span>
                            </div>
                            <div class="mono" style="margin-top: 6px; font-size: 11.5px; color: var(--ink-3);">
                                {{ $entry->guest_phone ?: ($entry->guest?->phone ?: '—') }}
                                @if ($entry->guest_email && ! str_ends_with($entry->guest_email, '@example.invalid'))
                                    · {{ $entry->guest_email }}
                                @endif
                            </div>
                            <p style="margin: 9px 0 0; font-size: 13px; color: var(--ink-2); line-height: 1.55; white-space: pre-line;">{{ $entry->description }}</p>
                            <div class="mono" style="margin-top: 8px; font-size: 11px; color: var(--ink-3);">
                                {{ __('Reported by') }}: {{ $entry->tenant?->business_name ?? __('a homestay') }}
                                · {{ $entry->created_at?->timezone(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->format('M j, Y') }}
                                @if ($entry->reviewed_at)
                                    · {{ __('reviewed') }} {{ $entry->reviewed_at->timezone(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->format('M j, Y') }}
                                    @if ($entry->reviewedByUser) {{ __('by') }} {{ $entry->reviewedByUser->name }} @endif
                                @endif
                            </div>
                            @if (trim((string) $entry->admin_notes) !== '')
                                <div style="margin-top: 6px; font-size: 12px; color: var(--ink-2);"><strong>{{ __('Admin note') }}:</strong> {{ $entry->admin_notes }}</div>
                            @endif
                        </div>

                        <div style="flex-shrink:0;">
                            <form method="POST" action="{{ route('platform.blacklist.review', $entry->id) }}" style="display:flex; flex-direction:column; gap: 8px; width: 220px;">
                                @csrf
                                <input type="text" name="admin_notes" maxlength="1000" class="input" style="height:32px; font-size:12px;" placeholder="{{ __('Optional note…') }}" value="{{ $entry->admin_notes }}">
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    @if ($entry->review_status === \App\Models\GuestBlacklistEntry::STATUS_PENDING)
                                        <button type="submit" name="decision" value="approve" class="btn btn-primary btn-sm">{{ __('Verify') }}</button>
                                        <button type="submit" name="decision" value="reject" class="btn btn-sm">{{ __('Reject') }}</button>
                                    @elseif ($entry->review_status === \App\Models\GuestBlacklistEntry::STATUS_APPROVED)
                                        <span class="pill pill-ok" style="height:22px;">{{ __('Verified — flagged everywhere') }}</span>
                                        <button type="submit" name="decision" value="revoke" class="btn btn-sm" style="color: var(--err); border-color: var(--err);">{{ __('Remove flag') }}</button>
                                    @else
                                        <span class="pill" style="height:22px; color: var(--ink-3);">{{ __('Rejected') }}</span>
                                        <button type="submit" name="decision" value="approve" class="btn btn-sm">{{ __('Verify instead') }}</button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="padding: 40px; text-align:center; color: var(--ink-3);">{{ __('No reports here.') }}</div>
                @endforelse
            </div>

            @if ($entries->hasPages())
                <div style="padding: 14px 18px; border-top: .5px solid var(--line);">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
