<x-app-layout :title="$guest->name">
    <div style="display:flex; flex-direction:column; gap: 20px; max-width: 860px;">

        {{-- Back --}}
        <div>
            <a href="{{ route('tenant.guests.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Guests') }}</a>
            <div style="display:flex; align-items:center; gap: 14px; margin-top: 6px;">
                <x-avatar :name="$guest->name" :size="44"/>
                <div>
                    <div class="kicker">{{ __('Guest profile') }}</div>
                    <h2 class="display-2" style="margin: 2px 0 0;">{{ $guest->name }}</h2>
                    <div style="margin-top: 4px; color: var(--ink-3); font-size: 13px;">
                        <span class="mono">{{ $guest->phone ?: '—' }}</span>
                        @if ($guest->email && ! str_ends_with($guest->email, '@example.invalid'))
                            · {{ $guest->email }}
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-left: 3px solid var(--ok); background: var(--ok-tint); font-size: 13px;">
                {{ session('status') }}
            </div>
        @endif

        {{-- Platform-wide blacklist alert --}}
        @if ($verified->isNotEmpty())
            <div class="hauz-card" style="padding: 16px 18px; border-left: 4px solid var(--err); background: var(--err-tint);">
                <div style="display:flex; align-items:center; gap: 8px; color: var(--err); font-weight: 700; font-size: 14px;">
                    <x-icon name="alert" :size="16"/>
                    {{ __('This guest is flagged platform-wide') }}
                </div>
                <div style="margin-top: 4px; font-size: 12.5px; color: var(--ink-2);">
                    {{ __('Verified by the Tempahlah team from reports by other homestays. Review before accepting a booking.') }}
                </div>
                <div style="display:flex; flex-direction:column; gap: 10px; margin-top: 14px;">
                    @foreach ($verified as $f)
                        <div style="background: var(--bg-elev); border: .5px solid var(--line); border-radius: 10px; padding: 12px 14px;">
                            <div style="display:flex; align-items:center; gap: 8px; flex-wrap: wrap;">
                                <x-pill :variant="$f->severityVariant()" :dot="true">{{ __($f->severityLabel()) }}</x-pill>
                                <span style="font-weight: 600; font-size: 13px;">{{ __($f->reasonLabel()) }}</span>
                                <span style="font-size: 11.5px; color: var(--ink-3); margin-left:auto;">
                                    {{ optional($f->reviewed_at)->format('M j, Y') }}
                                </span>
                            </div>
                            <p style="margin: 8px 0 0; font-size: 13px; color: var(--ink-2); white-space: pre-line;">{{ $f->description }}</p>
                            <div style="margin-top: 8px; font-size: 11.5px; color: var(--ink-3);">
                                {{ __('Reported by') }}: {{ $f->tenant?->business_name ?? __('a homestay') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Stay stats with this homestay --}}
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: 14px;">
            <div class="hauz-card" style="padding: 16px;">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Stays here') }}</div>
                <div class="display-3" style="line-height:1;">{{ $stays }}</div>
            </div>
            <div class="hauz-card" style="padding: 16px;">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Nights') }}</div>
                <div class="display-3" style="line-height:1;">{{ $nights }}</div>
            </div>
            <div class="hauz-card" style="padding: 16px;">
                <div class="kicker" style="margin-bottom: 8px;">{{ __('Lifetime spend') }}</div>
                <div class="display-3" style="line-height:1;"><span class="mono" style="font-size:14px; color:var(--ink-3);">RM</span> {{ number_format($spend, 0) }}</div>
            </div>
        </div>

        {{-- Report / blacklist form --}}
        <div class="hauz-card" style="padding: 18px;">
            <div class="kicker" style="margin-bottom: 4px;">{{ __('Report this guest') }}</div>
            <div style="font-family: var(--font-display); font-size: 18px; font-weight: 600;">{{ __('Blacklist / feedback') }}</div>
            <p style="margin: 6px 0 14px; font-size: 12.5px; color: var(--ink-3);">
                {{ __('Tell us what happened (damage, non-payment, kurang ajar, etc.). Our team reviews every report before it flags this guest for other homestays.') }}
            </p>

            <form method="POST" action="{{ route('tenant.guests.report', $guest->id) }}" style="display:flex; flex-direction:column; gap: 14px;">
                @csrf
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 14px;">
                    <label style="display:block;">
                        <span style="display:block; font-size: 12px; font-weight: 600; margin-bottom: 6px;">{{ __('Severity') }}</span>
                        <select name="severity" class="input" required>
                            @foreach ($severities as $key => $label)
                                <option value="{{ $key }}" @selected($key === \App\Models\GuestBlacklistEntry::SEVERITY_WARNING)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="display:block;">
                        <span style="display:block; font-size: 12px; font-weight: 600; margin-bottom: 6px;">{{ __('What did they do?') }}</span>
                        <select name="reason_code" class="input" required>
                            @foreach ($reasons as $key => $label)
                                <option value="{{ $key }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label style="display:block;">
                    <span style="display:block; font-size: 12px; font-weight: 600; margin-bottom: 6px;">{{ __('Describe what happened') }}</span>
                    <textarea name="description" class="input" rows="4" required minlength="10" maxlength="2000"
                              style="resize: vertical; min-height: 96px;"
                              placeholder="{{ __('e.g. Broke two chairs and refused to pay for the damage. Very rude to staff.') }}">{{ old('description') }}</textarea>
                    @error('description')<span style="color: var(--err); font-size: 11.5px;">{{ $message }}</span>@enderror
                </label>
                <div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Submit report') }}</button>
                </div>
            </form>
        </div>

        {{-- This tenant's own reports --}}
        @if ($myReports->isNotEmpty())
            <div class="hauz-card" style="padding: 18px;">
                <div class="kicker" style="margin-bottom: 12px;">{{ __('Your reports on this guest') }}</div>
                <div style="display:flex; flex-direction:column; gap: 10px;">
                    @foreach ($myReports as $r)
                        <div style="border: .5px solid var(--line); border-radius: 10px; padding: 12px 14px;">
                            <div style="display:flex; align-items:center; gap: 8px; flex-wrap: wrap;">
                                <x-pill :variant="$r->severityVariant()">{{ __($r->severityLabel()) }}</x-pill>
                                <span style="font-weight: 600; font-size: 13px;">{{ __($r->reasonLabel()) }}</span>
                                @php $sv = ['pending' => 'warn', 'approved' => 'ok', 'rejected' => 'default', 'overturned' => 'default'][$r->review_status] ?? 'default'; @endphp
                                <x-pill :variant="$sv" style="margin-left:auto;">
                                    {{ __(ucfirst($r->review_status === 'approved' ? 'verified' : $r->review_status)) }}
                                </x-pill>
                            </div>
                            <p style="margin: 8px 0 0; font-size: 12.5px; color: var(--ink-2); white-space: pre-line;">{{ $r->description }}</p>
                            <div style="margin-top: 6px; font-size: 11px; color: var(--ink-3);">{{ $r->created_at->format('M j, Y') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Stay history --}}
        <div class="hauz-card" style="padding: 0;">
            <div style="padding: 14px 18px; border-bottom: .5px solid var(--line);">
                <div class="kicker">{{ __('Stay history') }}</div>
            </div>
            <div style="display:flex; flex-direction:column;">
                @foreach ($bookings as $b)
                    <a href="{{ route('tenant.bookings.show', $b->id) }}"
                       style="display:flex; align-items:center; justify-content:space-between; gap: 12px; padding: 12px 18px; border-top: .5px solid var(--line); text-decoration:none; color: inherit;">
                        <div>
                            <div style="font-weight: 500; font-size: 13px;">{{ $b->property?->name ?? '—' }}</div>
                            <div style="font-size: 11.5px; color: var(--ink-3);" class="mono">
                                {{ $b->check_in->format('M j') }} → {{ $b->check_out->format('M j, Y') }}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="mono" style="font-weight: 500; font-size: 13px;">RM {{ number_format($b->total_amount, 0) }}</div>
                            <div style="font-size: 11px; color: var(--ink-3);">{{ ucfirst(str_replace('_', ' ', $b->status)) }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
