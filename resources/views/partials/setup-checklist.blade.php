{{--
    First-run setup checklist. Rendered from App\Services\Onboarding\SetupChecklist,
    which derives every step from live state — so this card cannot claim a step is
    done while the underlying thing is broken, and it removes itself once the
    tenant is genuinely ready to take bookings.

    Expects: $checklist (array|null) from the Dashboard Livewire component.
--}}
@if ($checklist && ! $checklist['complete'])
    @once
        <style>
            .su-card { padding: 18px 20px 16px; }
            .su-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
            .su-title { font-size:16px; font-weight:600; color:var(--ink); margin:2px 0 4px; }
            .su-sub { font-size:12.5px; color:var(--ink-3); }
            .su-progress { min-width:150px; flex:0 0 auto; }
            .su-count { font-size:12px; color:var(--ink-3); text-align:right; margin-bottom:6px; font-variant-numeric:tabular-nums; }
            .su-bar { height:6px; border-radius:99px; background:var(--bg-sunk); overflow:hidden; }
            .su-bar-fill { height:100%; border-radius:99px; background:var(--primary); transition:width .3s ease; }
            .su-list { list-style:none; margin:16px 0 0; padding:0; display:flex; flex-direction:column; gap:2px; }
            .su-step { display:flex; align-items:center; gap:12px; padding:10px 8px; border-radius:var(--r-md); }
            .su-step:hover { background:var(--bg-sunk); }
            .su-tick { flex:0 0 auto; width:22px; height:22px; border-radius:99px; display:grid; place-items:center;
                       border:1.5px solid var(--line); background:var(--bg-elev); }
            .su-step-done .su-tick { background:var(--ok); border-color:var(--ok); }
            .su-tick svg { width:13px; height:13px; stroke:#fff; stroke-width:3; fill:none; }
            .su-body { flex:1 1 auto; min-width:0; }
            .su-step-title { font-size:13.5px; font-weight:500; color:var(--ink); }
            .su-step-done .su-step-title { color:var(--ink-3); text-decoration:line-through; }
            .su-step-body { font-size:11.5px; color:var(--ink-3); margin-top:1px; }
            .su-step-done .su-step-body { display:none; }
            .su-cta { flex:0 0 auto; font-size:12px; font-weight:500; color:var(--primary); text-decoration:none; white-space:nowrap; }
            .su-cta:hover { text-decoration:underline; }
            .su-cta-btn { background:none; border:none; padding:0; cursor:pointer; font-family:inherit; }
            .su-share { color:#fff; background:var(--primary); padding:7px 12px; border-radius:var(--r-md); }
            .su-share:hover { text-decoration:none; background:var(--primary-hover, var(--primary)); }
            .su-cta-locked { flex:0 0 auto; font-size:12px; color:var(--ink-3); white-space:nowrap; display:inline-flex; align-items:center; gap:5px; }
            .su-cta-locked svg { width:12px; height:12px; stroke:currentColor; stroke-width:2; fill:none; }
            .su-step-locked .su-step-title { color:var(--ink-3); }
            .su-foot { margin-top:14px; padding-top:12px; border-top:.5px solid var(--line);
                       display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
            .su-replay { background:none; border:none; padding:0; cursor:pointer; font-size:12px; color:var(--ink-3); text-decoration:underline; }
            .su-replay:hover { color:var(--ink); }
            .su-note { font-size:11.5px; color:var(--ink-3); }
            @media (max-width:640px) {
                .su-progress { min-width:0; width:100%; }
                .su-count { text-align:left; }
                .su-cta { font-size:11.5px; }
                .su-step { padding:10px 4px; gap:10px; }
            }
        </style>
    @endonce

    @php $pct = $checklist['total'] ? round($checklist['done'] / $checklist['total'] * 100) : 0; @endphp

    <div class="hauz-card su-card">
        <div class="su-head">
            <div>
                <div class="kicker">{{ __('Get set up') }}</div>
                <div class="su-title">{{ __('Finish these to start taking bookings') }}</div>
                <div class="su-sub">{{ __('Each step ticks itself once it is really done.') }}</div>
            </div>
            <div class="su-progress">
                <div class="su-count">{{ $checklist['done'] }} / {{ $checklist['total'] }} {{ __('done') }}</div>
                <div class="su-bar"><div class="su-bar-fill" style="width: {{ $pct }}%"></div></div>
            </div>
        </div>

        <ul class="su-list">
            @foreach ($checklist['steps'] as $step)
                @php $locked = $step['locked'] ?? false; @endphp
                <li class="su-step {{ $step['done'] ? 'su-step-done' : '' }} {{ $locked ? 'su-step-locked' : '' }}">
                    <span class="su-tick">
                        @if ($step['done'])
                            <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        @endif
                    </span>
                    <div class="su-body">
                        <div class="su-step-title">{{ $step['title'] }}</div>
                        <div class="su-step-body">{{ $step['body'] }}</div>
                    </div>

                    @if (! $step['done'])
                        @if ($step['key'] === 'booking')
                            @if ($locked)
                                {{-- Not shareable until the core steps are green. --}}
                                <span class="su-cta-locked" aria-disabled="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>
                                    {{ __('Locked') }}
                                </span>
                            @else
                                {{-- Open the public booking page in a new tab (user gesture,
                                     so it isn't popup-blocked) AND record the share, which
                                     re-renders and dismisses this whole card. --}}
                                <button type="button"
                                        class="su-cta su-cta-btn su-share"
                                        x-data
                                        @click="window.open(@js($checklist['public_url']), '_blank', 'noopener'); $wire.shareBookingLink()">
                                    {{ $step['cta'] }} →
                                </button>
                            @endif
                        @elseif ($step['route'])
                            <a class="su-cta" href="{{ $step['route'] }}">{{ $step['cta'] }} →</a>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="su-foot">
            <span class="su-note">{{ __('Need a refresher on where things live?') }}</span>
            <form method="POST" action="{{ route('tenant.onboarding.replay') }}">
                @csrf
                <button type="submit" class="su-replay">{{ __('Replay the walkthrough') }}</button>
            </form>
        </div>
    </div>
@endif
