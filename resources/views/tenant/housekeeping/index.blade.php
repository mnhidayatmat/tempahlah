<x-app-layout :title="__('Housekeeping')">
    @php
        $cleaningStatusUI = [
            'pending'     => ['bg' => 'var(--bg-sunk)',     'color' => 'var(--ink-2)',  'label' => __('Scheduled')],
            'in_progress' => ['bg' => 'var(--accent-tint)', 'color' => 'var(--accent)', 'label' => __('In progress')],
            'completed'   => ['bg' => 'var(--ok-tint)',     'color' => 'var(--ok)',     'label' => __('Done')],
            'skipped'     => ['bg' => 'var(--err-tint)',    'color' => 'var(--err)',    'label' => __('Skipped')],
        ];
        $laundryStatusUI = [
            'pending'   => ['bg' => 'var(--bg-sunk)',     'color' => 'var(--ink-2)',  'label' => __('Pending pickup')],
            'picked_up' => ['bg' => 'var(--accent-tint)', 'color' => 'var(--accent)', 'label' => __('In wash')],
            'returned'  => ['bg' => 'var(--ok-tint)',     'color' => 'var(--ok)',     'label' => __('Returned')],
            'cancelled' => ['bg' => 'var(--err-tint)',    'color' => 'var(--err)',    'label' => __('Cancelled')],
        ];
        $maintenancePriorityColor = [
            'low'    => 'var(--ink-3)',
            'medium' => 'var(--warn)',
            'high'   => 'var(--err)',
            'urgent' => 'var(--err)',
        ];
        $maintenanceStatusUI = [
            'open'        => ['bg' => 'var(--warn-tint)', 'color' => 'oklch(45% 0.13 75)', 'label' => __('Open')],
            'in_progress' => ['bg' => 'var(--accent-tint)', 'color' => 'var(--accent)',     'label' => __('In progress')],
            'resolved'    => ['bg' => 'var(--ok-tint)',     'color' => 'var(--ok)',         'label' => __('Resolved')],
            'closed'      => ['bg' => 'var(--bg-sunk)',     'color' => 'var(--ink-3)',      'label' => __('Closed')],
        ];
    @endphp

    @once
    <style>
        .hk-wrap{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
        /* Tab strip scrolls itself on narrow screens rather than widening the page. */
        .hk-tabs{ scrollbar-width:none; }
        .hk-tabs::-webkit-scrollbar{ display:none; }
        .hk-tabs > a{ flex:none; white-space:nowrap; }
        .hk-table{ width:100%; border-collapse:collapse; font-size:12.5px; min-width:760px; }
        .hk-table thead th{
            text-align:left; padding:9px 14px; font-size:10px; font-weight:600;
            text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3);
            background:var(--bg-sunk); border-bottom:.5px solid var(--line); white-space:nowrap;
        }
        .hk-table tbody td{ padding:12px 14px; border-top:.5px solid var(--line); vertical-align:middle; color:var(--ink-2); }
        .hk-table tbody:first-of-type td{ border-top:0; }
        .hk-table .hk-num{ text-align:right; white-space:nowrap; }
        .hk-cost{ font-variant-numeric:tabular-nums; color:var(--ink); font-weight:500; }
        .hk-actions{ display:flex; gap:4px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
        .hk-ref{ font-size:10.5px; color:var(--ink-3); margin-top:2px; }
        .hk-empty{ padding:32px; text-align:center; color:var(--ink-3); font-size:13px; }

        /* ---- Phones: every table row becomes a labelled card -----------------
           Swiping a 770px table sideways is miserable on a phone, so below 768px
           the table collapses: the <thead> is hidden and each <td> shows its
           column name (from data-label) beside the value. One markup source, so
           the desktop table and the mobile cards can never drift apart.
           Alpine's x-show sets an inline `display:none`, which still beats these
           rules, so the inline edit rows keep working. */
        @media (max-width: 768px) {
            .hk-wrap{ overflow-x:visible; }
            .hk-table{ min-width:0; display:block; font-size:13px; }
            .hk-table thead{ display:none; }
            .hk-table tbody{ display:block; border-top:.5px solid var(--line); }
            .hk-table tbody:first-of-type{ border-top:0; }
            .hk-table tfoot{ display:block; border-top:1px solid var(--line); }
            .hk-table tbody tr, .hk-table tfoot tr{ display:block; }
            .hk-table tbody tr + tr{ border-top:.5px solid var(--line); }
            .hk-table tbody td, .hk-table tfoot td{
                display:flex; align-items:baseline; justify-content:space-between; gap:14px;
                border-top:0; padding:5px 14px; text-align:left; white-space:normal;
            }
            .hk-table tbody tr td:first-child{ padding-top:12px; }
            .hk-table tbody tr td:last-child{ padding-bottom:12px; }
            .hk-table td[data-label]::before{
                content:attr(data-label); flex:none;
                font-size:10.5px; font-weight:600; letter-spacing:.05em;
                text-transform:uppercase; color:var(--ink-3);
            }
            /* Action buttons + the edit-form cell carry no label. */
            .hk-table tbody td:not([data-label]){ justify-content:flex-end; }
            /* …except the row's heading cell, which spans the card. */
            .hk-table td.hk-title{ display:block; padding-top:12px; font-weight:600; }
            .hk-table td[colspan]{ display:block; }
            .hk-hide-mobile{ display:none; }
            /* The create + inline-edit forms are 4-column grids whose children
               carry `grid-column: span N`. Stacked to one column, those spans
               would create implicit extra columns and push the page sideways,
               so every field goes full-width instead. */
            .hk-form-grid{ grid-template-columns: 1fr !important; }
            .hk-form-grid > *{ grid-column: 1 / -1 !important; }
            .hk-table .hk-num{ text-align:left; white-space:normal; }
            .hk-actions{ justify-content:flex-end; }
        }
        .hk-tfoot td{ padding:10px 14px; border-top:.5px solid var(--line); background:var(--bg-sunk); font-size:11.5px; color:var(--ink-3); }
        .hk-tfoot .hk-cost{ font-size:12.5px; }
        /* Auto-generate switch */
        .hk-switch{ display:inline-flex; align-items:center; gap:9px; cursor:pointer; user-select:none; font-size:13px; font-weight:500; color:var(--ink); }
        .hk-switch input{ position:absolute; opacity:0; width:0; height:0; }
        .hk-switch-track{ position:relative; width:38px; height:22px; border-radius:999px; background:var(--bg-sunk); border:.5px solid var(--line); transition:background .15s, border-color .15s; flex-shrink:0; }
        .hk-switch-thumb{ position:absolute; top:2px; left:2px; width:16px; height:16px; border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.25); transition:transform .15s; }
        .hk-switch input:checked + .hk-switch-track{ background:var(--primary); border-color:var(--primary); }
        .hk-switch input:checked + .hk-switch-track .hk-switch-thumb{ transform:translateX(16px); }
        .hk-switch input:focus-visible + .hk-switch-track{ box-shadow:0 0 0 3px var(--primary-tint); }
    </style>
    @endonce

    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Operations') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Housekeeping') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ $cleaningStats['today'] }} {{ __('tasks today') }}
                    · {{ $cleaningStats['issues'] }} {{ __('flagged') }}
                    · {{ $laundryStats['in_progress'] }} {{ __('laundry batches in cycle') }}
                </div>
            </div>
            @php $canAutoHousekeep = \Laravel\Pennant\Feature::active('auto_operational_tasks'); @endphp
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                @if ($canAutoHousekeep)
                    {{-- Auto-generate toggle: when on, every new confirmed booking auto-schedules cleaning + laundry --}}
                    <form method="POST" action="{{ route('tenant.housekeeping.auto-toggle') }}"
                          class="hauz-card" style="padding:8px 12px; margin:0;">
                        @csrf
                        <label class="hk-switch" title="{{ __('When on, a confirmed booking automatically schedules its cleaning + laundry.') }}">
                            <input type="checkbox" name="auto_housekeeping" value="1" @checked($autoHousekeeping) onchange="this.form.submit()">
                            <span class="hk-switch-track"><span class="hk-switch-thumb"></span></span>
                            <span class="hk-switch-label">{{ __('Generate from bookings') }}</span>
                        </label>
                    </form>
                    <form method="POST" action="{{ route('tenant.housekeeping.generate') }}"
                          onsubmit="return confirm('{{ __('Auto-schedule cleaning + laundry for all upcoming confirmed bookings? Existing tasks are kept.') }}')">
                        @csrf
                        {{-- Walks every upcoming confirmed booking, so it scales with the
                             tenant's calendar rather than being a constant-time write. --}}
                        <x-btn-submit class="btn btn-sm">{{ __('Generate now for existing bookings') }}</x-btn-submit>
                    </form>
                @else
                    {{-- Auto-scheduling is a Pro feature. Free tenants schedule tasks by
                         hand below; show an upgrade prompt instead of the controls. --}}
                    <a href="{{ route('tenant.subscription') }}" class="hauz-card"
                       style="display:flex; align-items:center; gap:8px; padding:8px 12px; margin:0; text-decoration:none; color:var(--pro); background:var(--pro-tint); font-size:12px;">
                        <x-icon name="lock" :size="13"/>
                        <span>{{ __('Auto-schedule from bookings — Pro') }}</span>
                    </a>
                @endif
                <a href="{{ route('tenant.housekeeping.print') }}" target="_blank" rel="noopener" class="btn btn-sm">{{ __("Print today's run sheet") }}</a>
            </div>
        </div>
        <div style="margin-top:-8px; color: var(--ink-3); font-size: 12px;">
            {{ __('Turn on “Generate from bookings” and every new confirmed booking automatically schedules its cleaning + laundry — full clean 30 min after check-out (2 cleaners if the next guest is within 2 days, else 1), pre-arrival dusting when the house sat empty 3+ days, and a laundry batch. Use “Generate now” to backfill your existing bookings. Edit or share any task below.') }}
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <ul style="margin:0; padding-left: 18px;">@foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Tabs. Four labels don't fit a 360px phone, so the strip scrolls on its
             own instead of pushing the whole page sideways. `flex-shrink:0` on the
             links stops them squashing; the scrollbar is hidden (.hk-tabs). --}}
        <div class="hk-tabs" style="display:flex; gap: 2px; border-bottom: .5px solid var(--line); overflow-x: auto; -webkit-overflow-scrolling: touch;">
            @foreach ([
                ['key' => 'cleaning',    'label' => __('Cleaning'),    'count' => $cleaningStats['today']],
                ['key' => 'laundry',     'label' => __('Laundry'),     'count' => $laundryStats['in_progress'] + $laundryStats['pending']],
                ['key' => 'maintenance', 'label' => __('Maintenance'), 'count' => $maintenanceStats['open'] + $maintenanceStats['in_progress']],
                ['key' => 'history',     'label' => __('History & costs'), 'count' => null],
            ] as $t)
                @php $active = $tab === $t['key']; @endphp
                <a href="{{ route('tenant.housekeeping.index', ['tab' => $t['key']]) }}" style="
                    padding: 10px 16px; border: 0; background: transparent;
                    text-decoration: none; font-size: 13px; font-weight: 500;
                    color: {{ $active ? 'var(--ink)' : 'var(--ink-3)' }};
                    border-bottom: 2px solid {{ $active ? 'var(--primary)' : 'transparent' }};
                    margin-bottom: -1px; display:inline-flex; align-items:center; gap: 6px;">
                    {{ $t['label'] }}
                    @if (! is_null($t['count']))
                        <span style="background: {{ $active ? 'var(--primary-tint)' : 'var(--bg-sunk)' }};
                                     color: {{ $active ? 'var(--primary)' : 'var(--ink-3)' }};
                                     padding: 1px 6px; border-radius: 999px; font-size: 10.5px; font-weight: 600;">
                            {{ $t['count'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Cleaning tab --}}
        @if ($tab === 'cleaning')
            <div style="display:flex; flex-direction:column; gap: 18px;">
                {{-- Stats --}}
                <div class="hk-form-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                    @foreach ([
                        [__('Today'), $cleaningStats['today'], __('turnovers + refreshes'), null],
                        [__('In progress'), $cleaningStats['in_progress'], __('being cleaned now'), 'var(--accent)'],
                        [__('Completed today'), $cleaningStats['completed'], __('of :total scheduled', ['total' => $cleaningStats['today']]), 'var(--ok)'],
                        [__('Flagged issues'), $cleaningStats['issues'], __('need owner review'), 'var(--err)'],
                    ] as [$label, $value, $sub, $tone])
                        <div class="hauz-card" style="padding: 14px;">
                            <div class="kicker" style="margin-bottom: 6px;">{{ $label }}</div>
                            <div style="font-size: 24px; font-weight: 600; line-height: 1; color: {{ $tone ?? 'var(--ink)' }};">{{ $value }}</div>
                            <div style="margin-top: 4px; font-size: 11px; color: var(--ink-3);">{{ $sub }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Inline new-cleaning-task form --}}
                <details class="hauz-card" style="padding: 0; overflow: hidden;">
                    <summary style="cursor: pointer; padding: 12px 16px; background: var(--bg-sunk); display:flex; align-items:center; gap: 8px; font-size: 13px; font-weight: 500; user-select: none;">
                        <x-icon name="plus" :size="13"/> {{ __('Schedule a new cleaning task') }}
                    </summary>
                    <form method="POST" action="{{ route('tenant.housekeeping.cleaning.store') }}"
                          x-data="{
                              times: {{ $propertyTimes->toJson() }},
                              date: '{{ $scheduleDate->format('Y-m-d') }}',
                              pid: '{{ $properties->count() === 1 ? $properties->first()->id : '' }}',
                              addHours(hhmm, h){ let [H,M] = (hhmm||'12:00').split(':').map(Number); H=((H+h)%24+24)%24; return String(H).padStart(2,'0')+':'+String(M).padStart(2,'0'); },
                              apply(){ const t = this.times[this.pid] || { check_out: '12:00' }; this.$refs.sched.value = this.date + 'T' + this.addHours(t.check_out, 1); }
                          }"
                          x-init="$nextTick(() => apply())"
                          class="hk-form-grid"
                          style="padding: 16px; display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                        @csrf
                        <div style="grid-column: span 2;">
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Property') }} *</label>
                            <select name="property_id" class="input" required x-model="pid" @change="apply()">
                                @if ($properties->count() !== 1)
                                    <option value="">—</option>
                                @endif
                                @foreach ($properties as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Type') }} *</label>
                            <select name="type" class="input" required>
                                <option value="full">{{ __('Full turnover') }}</option>
                                <option value="light">{{ __('Light refresh') }}</option>
                                <option value="deep">{{ __('Deep clean') }}</option>
                                <option value="pool">{{ __('Pool / outdoor') }}</option>
                                <option value="post_event">{{ __('Post-event') }}</option>
                                <option value="pre_arrival">{{ __('Pre-arrival dusting') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Scheduled at') }} *</label>
                            <input type="datetime-local" name="scheduled_at" class="input" required x-ref="sched">
                            <div style="font-size: 10.5px; color: var(--ink-3); margin-top: 3px;">{{ __('Auto-set to ~1h after check-out') }}</div>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cleaner') }}</label>
                            <select name="cleaner_id" class="input">
                                <option value="">{{ __('— Unassigned —') }}</option>
                                @foreach ($cleaners as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                @endforeach
                            </select>
                            @if ($cleaners->isEmpty())
                                <div style="font-size: 10.5px; color: var(--ink-3); margin-top: 3px;">
                                    <a href="{{ route('tenant.directory.index', ['tab' => 'cleaners']) }}" style="color: var(--primary);">{{ __('Register a cleaner') }}</a>
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cleaners needed') }}</label>
                            <input type="number" name="cleaners_required" class="input" min="1" max="20" step="1" value="1">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Duration (hours)') }}</label>
                            <input type="number" name="duration_hours" class="input" min="0.5" max="24" step="0.5" placeholder="e.g. 2">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cost (RM)') }}</label>
                            <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01" placeholder="0.00">
                        </div>
                        <div style="grid-column: span 2;">
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Notes') }}</label>
                            <textarea name="notes" class="input" maxlength="2000" rows="2"
                                      placeholder="{{ __('Optional handoff notes — press Enter for a new line, e.g. 1. … 2. … 3. …') }}"
                                      x-init="$nextTick(() => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px' })"
                                      @input="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                      @focus="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                      style="resize:none; overflow:hidden; min-height:40px; line-height:1.45;"></textarea>
                        </div>
                        <div style="grid-column: span 4; text-align: right;">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Schedule task') }}</button>
                        </div>
                    </form>
                </details>

                {{-- Today --}}
                <div>
                    <div style="font-size: 14px; font-weight: 600;">{{ __('Today') }} · {{ $today->format('l j F Y') }}</div>
                    <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('Live status from cleaner mobile app') }}</div>
                    <div class="hauz-card hk-wrap" style="padding: 0; margin-top: 10px;">
                        <table class="hk-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Property') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Scheduled') }}</th>
                                    <th>{{ __('Cleaner') }}</th>
                                    <th>{{ __('Crew') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="hk-num">{{ __('Cost') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            @forelse ($todayTasks as $t)
                                <x-housekeeping.cleaning-row :task="$t" :properties="$properties" :cleaners="$cleaners" :copy-text="$cleaningCopy[$t->id] ?? ''" :share-url="$cleaningShare[$t->id] ?? null"/>
                            @empty
                                <tbody><tr><td colspan="8" class="hk-empty">{{ __('No cleaning tasks scheduled today.') }}</td></tr></tbody>
                            @endforelse
                            @if ($todayTasks->isNotEmpty())
                                <tfoot class="hk-tfoot"><tr>
                                    <td colspan="6">{{ $todayTasks->count() }} {{ trans_choice('task|tasks', $todayTasks->count()) }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format((float) $todayTasks->sum('cost'), 2) }}</td>
                                    <td></td>
                                </tr></tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- Upcoming --}}
                @if ($upcoming->isNotEmpty())
                    <div>
                        <div style="display:flex; align-items:baseline; gap: 8px;">
                            <div style="font-size: 14px; font-weight: 600;">{{ __('Upcoming') }}</div>
                            <div style="font-size: 12px; color: var(--ink-3);">{{ $upcoming->count() }} {{ __('scheduled') }}</div>
                        </div>
                        <div class="hauz-card hk-wrap" style="padding: 0; margin-top: 10px;">
                            <table class="hk-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Property') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Scheduled') }}</th>
                                        <th>{{ __('Cleaner') }}</th>
                                        <th>{{ __('Crew') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th class="hk-num">{{ __('Cost') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                @foreach ($upcoming as $t)
                                    <x-housekeeping.cleaning-row :task="$t" :properties="$properties" :cleaners="$cleaners" :copy-text="$cleaningCopy[$t->id] ?? ''" :share-url="$cleaningShare[$t->id] ?? null"/>
                                @endforeach
                                <tfoot class="hk-tfoot"><tr>
                                    <td colspan="6">{{ $upcoming->count() }} {{ trans_choice('task|tasks', $upcoming->count()) }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format((float) $upcoming->sum('cost'), 2) }}</td>
                                    <td></td>
                                </tr></tfoot>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Laundry tab --}}
        @if ($tab === 'laundry')
            <div style="display:flex; flex-direction:column; gap: 18px;">
                <div class="hk-form-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                    @foreach ([
                        [__('Pending pickup'), $laundryStats['pending'], __('awaiting cleaner drop-off'), 'var(--warn)'],
                        [__('In wash'), $laundryStats['in_progress'], __('vendors processing'), 'var(--accent)'],
                        [__('Returned'), $laundryStats['ready'], __('cycle complete'), 'var(--ok)'],
                        [__('Items in flight'), $laundryStats['total_items'], __('across all batches'), null],
                    ] as [$label, $value, $sub, $tone])
                        <div class="hauz-card" style="padding: 14px;">
                            <div class="kicker" style="margin-bottom: 6px;">{{ $label }}</div>
                            <div style="font-size: 24px; font-weight: 600; line-height: 1; color: {{ $tone ?? 'var(--ink)' }};">{{ $value }}</div>
                            <div style="margin-top: 4px; font-size: 11px; color: var(--ink-3);">{{ $sub }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Inline log-laundry-batch form --}}
                <details class="hauz-card" style="padding: 0; overflow: hidden;">
                    <summary style="cursor: pointer; padding: 12px 16px; background: var(--bg-sunk); display:flex; align-items:center; gap: 8px; font-size: 13px; font-weight: 500; user-select: none;">
                        <x-icon name="plus" :size="13"/> {{ __('Log a new laundry batch') }}
                    </summary>
                    <form method="POST" action="{{ route('tenant.housekeeping.laundry.store') }}"
                          x-data="{
                              times: {{ $propertyTimes->toJson() }},
                              date: '{{ $scheduleDate->format('Y-m-d') }}',
                              pid: '{{ $properties->count() === 1 ? $properties->first()->id : '' }}',
                              addHours(hhmm, h){ let [H,M] = (hhmm||'12:00').split(':').map(Number); H=((H+h)%24+24)%24; return String(H).padStart(2,'0')+':'+String(M).padStart(2,'0'); },
                              nextDay(d){ const dt = new Date(d+'T00:00'); dt.setDate(dt.getDate()+1); return dt.getFullYear()+'-'+String(dt.getMonth()+1).padStart(2,'0')+'-'+String(dt.getDate()).padStart(2,'0'); },
                              apply(){ const t = this.times[this.pid] || { check_out: '12:00' }; this.$refs.pickup.value = this.date + 'T' + this.addHours(t.check_out, 2); this.$refs.ret.value = this.nextDay(this.date) + 'T' + t.check_out; }
                          }"
                          x-init="$nextTick(() => apply())"
                          class="hk-form-grid"
                          style="padding: 16px; display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                        @csrf
                        <div style="grid-column: span 2;">
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Property') }} *</label>
                            <select name="property_id" class="input" required x-model="pid" @change="apply()">
                                @if ($properties->count() !== 1)<option value="">—</option>@endif
                                @foreach ($properties as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Vendor') }}</label>
                            <select name="vendor_id" class="input">
                                <option value="">{{ __('— Unassigned —') }}</option>
                                @foreach ($laundryVendors as $v)
                                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                                @endforeach
                            </select>
                            @if ($laundryVendors->isEmpty())
                                <div style="font-size: 10.5px; color: var(--ink-3); margin-top: 3px;">
                                    <a href="{{ route('tenant.directory.index', ['tab' => 'vendors']) }}" style="color: var(--primary);">{{ __('Register a vendor') }}</a>
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Item count') }} *</label>
                            <input type="number" name="item_count" class="input" min="1" max="9999" required>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Pickup at') }} *</label>
                            <input type="datetime-local" name="pickup_at" class="input" required x-ref="pickup">
                            <div style="font-size: 10.5px; color: var(--ink-3); margin-top: 3px;">{{ __('Auto-set to ~2h after check-out') }}</div>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Expected return') }}</label>
                            <input type="datetime-local" name="expected_return_at" class="input" x-ref="ret">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cost (RM)') }}</label>
                            <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01" placeholder="0.00">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Notes') }}</label>
                            <textarea name="notes" class="input" maxlength="2000" rows="2"
                                      placeholder="{{ __('Press Enter for a new line, e.g. 1. … 2. … 3. …') }}"
                                      x-init="$nextTick(() => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px' })"
                                      @input="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                      @focus="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                      style="resize:none; overflow:hidden; min-height:40px; line-height:1.45;"></textarea>
                        </div>
                        <div style="grid-column: span 4; text-align: right;">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Log batch') }}</button>
                        </div>
                    </form>
                </details>

                <div>
                    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 12px;">
                        <div>
                            <div style="font-size: 14px; font-weight: 600;">{{ __('Active laundry batches') }}</div>
                            <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('Tracking pickup → wash → return per property') }}</div>
                        </div>
                    </div>
                    <div class="hauz-card hk-wrap" style="padding: 0; margin-top: 10px;">
                        <table class="hk-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Property') }}</th>
                                    <th>{{ __('Items') }}</th>
                                    <th>{{ __('Vendor') }}</th>
                                    <th>{{ __('Schedule') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="hk-num">{{ __('Cost') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            @forelse ($laundry as $l)
                                @php $ui = $laundryStatusUI[$l->status] ?? $laundryStatusUI['pending']; @endphp
                                <tbody x-data="{ editing: false }">
                                    {{-- Display row --}}
                                    <tr x-show="!editing">
                                        <td class="hk-title">
                                            <div style="font-weight: 500; font-size: 13px;">{{ $l->property?->name ?? '—' }}</div>
                                            <div class="hk-ref mono">LD-{{ str_pad($l->id, 4, '0', STR_PAD_LEFT) }}</div>
                                        </td>
                                        <td data-label="{{ __('Items') }}" style="white-space: nowrap;">{{ $l->item_count }} {{ __('items') }}</td>
                                        <td data-label="{{ __('Vendor') }}">{{ $l->vendor?->name ?? $l->vendor_name ?? __('Self-service') }}</td>
                                        <td data-label="{{ __('Schedule') }}" style="white-space: nowrap;">
                                            @if ($l->picked_up_at)
                                                <div>{{ __('Picked') }}: <span class="mono">{{ $l->picked_up_at->format('M j') }}</span></div>
                                            @else
                                                <div style="color: var(--warn);">{{ __('Awaiting pickup') }}</div>
                                            @endif
                                            @if ($l->expected_return_at)
                                                <div class="hk-ref" style="color: {{ $l->returned_at ? 'var(--ok)' : 'var(--ink-3)' }};">
                                                    {{ $l->returned_at ? __('Returned') : __('Expected') }}: <span class="mono">{{ ($l->returned_at ?? $l->expected_return_at)->format('M j') }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td data-label="{{ __('Status') }}">
                                            <span class="pill" style="background: {{ $ui['bg'] }}; color: {{ $ui['color'] }}; height: 18px; font-size: 10.5px;">
                                                <span class="pill-dot" style="background: {{ $ui['color'] }};"></span>{{ $ui['label'] }}
                                            </span>
                                        </td>
                                        <td class="hk-num hk-cost" data-label="{{ __('Cost') }}">{{ $l->cost !== null ? 'RM '.number_format($l->cost, 2) : '—' }}</td>
                                        <td>
                                            <div class="hk-actions">
                                                @if ($l->status === 'pending')
                                                    <form method="POST" action="{{ route('tenant.housekeeping.laundry.update', $l->id) }}">
                                                        @csrf @method('PATCH')<input type="hidden" name="action" value="pickup">
                                                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Mark picked up') }}</button>
                                                    </form>
                                                @elseif ($l->status === 'picked_up')
                                                    <form method="POST" action="{{ route('tenant.housekeeping.laundry.update', $l->id) }}">
                                                        @csrf @method('PATCH')<input type="hidden" name="action" value="return">
                                                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Mark returned') }}</button>
                                                    </form>
                                                @endif
                                                <x-housekeeping.share-button :url="$laundryShare[$l->id] ?? '#'"/>
                                                <x-housekeeping.copy-button :text="$laundryCopy[$l->id] ?? ''"/>
                                                <button type="button" class="btn btn-sm" @click="editing = true">{{ __('Edit') }}</button>
                                            </div>
                                        </td>
                                    </tr>

                                    {{-- Edit row --}}
                                    <tr x-show="editing" x-cloak>
                                        <td colspan="7" style="background: var(--bg-sunk); padding: 16px 14px;">
                                            <div style="font-weight: 600; font-size: 13px; margin-bottom: 12px;">{{ __('Edit laundry batch') }} · <span class="mono" style="color: var(--ink-3);">LD-{{ str_pad($l->id, 4, '0', STR_PAD_LEFT) }}</span></div>
                                            <form method="POST" action="{{ route('tenant.housekeeping.laundry.update', $l->id) }}" class="hk-form-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="action" value="edit">
                                                <div style="grid-column: span 2;">
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Property') }} *</label>
                                                    <select name="property_id" class="input" required>
                                                        @foreach ($properties as $p)
                                                            <option value="{{ $p->id }}" @selected($l->property_id == $p->id)>{{ $p->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Status') }} *</label>
                                                    <select name="status" class="input" required>
                                                        @foreach (['pending' => __('Pending pickup'), 'picked_up' => __('In wash'), 'returned' => __('Returned'), 'cancelled' => __('Cancelled')] as $val => $lbl)
                                                            <option value="{{ $val }}" @selected($l->status === $val)>{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Item count') }} *</label>
                                                    <input type="number" name="item_count" class="input" min="1" max="9999" required value="{{ $l->item_count }}">
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Vendor') }}</label>
                                                    <select name="vendor_id" class="input">
                                                        <option value="">{{ __('— Unassigned —') }}</option>
                                                        @foreach ($laundryVendors as $v)
                                                            <option value="{{ $v->id }}" @selected($l->vendor_id == $v->id)>{{ $v->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Pickup at') }} *</label>
                                                    <input type="datetime-local" name="pickup_at" class="input" required value="{{ $l->pickup_at?->format('Y-m-d\TH:i') }}">
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Expected return') }}</label>
                                                    <input type="datetime-local" name="expected_return_at" class="input" value="{{ $l->expected_return_at?->format('Y-m-d\TH:i') }}">
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cost (RM)') }}</label>
                                                    <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01" value="{{ $l->cost }}" placeholder="0.00">
                                                </div>
                                                <div style="grid-column: span 3;">
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Notes') }}</label>
                                                    <textarea name="notes" class="input" maxlength="2000" rows="2"
                                                              placeholder="{{ __('Press Enter for a new line, e.g. 1. … 2. … 3. …') }}"
                                                              x-init="$nextTick(() => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px' })"
                                                              @input="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                                              @focus="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                                              style="resize:none; overflow:hidden; min-height:40px; line-height:1.45;">{{ $l->notes }}</textarea>
                                                </div>
                                                <div style="grid-column: span 4; display:flex; justify-content:space-between; gap: 8px; align-items:center;">
                                                    <button type="submit" form="del-laundry-{{ $l->id }}" class="btn btn-sm" style="color: var(--err);">{{ __('Delete batch') }}</button>
                                                    <div style="display:flex; gap: 8px;">
                                                        <button type="button" class="btn btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                                                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                                                    </div>
                                                </div>
                                            </form>
                                            <form method="POST" id="del-laundry-{{ $l->id }}" action="{{ route('tenant.housekeeping.laundry.destroy', $l->id) }}" onsubmit="return confirm('{{ __('Delete this laundry batch?') }}')">
                                                @csrf @method('DELETE')
                                            </form>
                                        </td>
                                    </tr>
                                </tbody>
                            @empty
                                <tbody><tr><td colspan="7" class="hk-empty">{{ __('No laundry batches in the last 14 days.') }}</td></tr></tbody>
                            @endforelse
                            @if ($laundry->isNotEmpty())
                                <tfoot class="hk-tfoot"><tr>
                                    <td colspan="5">{{ $laundry->count() }} {{ trans_choice('batch|batches', $laundry->count()) }} · {{ $laundryStats['total_items'] }} {{ __('items') }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format((float) $laundry->sum('cost'), 2) }}</td>
                                    <td></td>
                                </tr></tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Maintenance tab --}}
        @if ($tab === 'maintenance')
            <div style="display:flex; flex-direction:column; gap: 18px;">
                <details class="hauz-card" style="padding: 0; overflow: hidden;">
                    <summary style="cursor: pointer; padding: 12px 16px; background: var(--bg-sunk); display:flex; align-items:center; gap: 8px; font-size: 13px; font-weight: 500; user-select: none;">
                        <x-icon name="plus" :size="13"/> {{ __('Log a new maintenance ticket') }}
                    </summary>
                    <form class="hk-form-grid" method="POST" action="{{ route('tenant.housekeeping.maintenance.store') }}"
                          style="padding: 16px; display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                        @csrf
                        <div style="grid-column: span 2;">
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Property') }} *</label>
                            <select name="property_id" class="input" required>
                                @if ($properties->count() !== 1)
                                    <option value="">—</option>
                                @endif
                                @foreach ($properties as $p)
                                    <option value="{{ $p->id }}" @selected($properties->count() === 1)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Priority') }} *</label>
                            <select name="priority" class="input" required>
                                <option value="low">{{ __('Low') }}</option>
                                <option value="medium" selected>{{ __('Medium') }}</option>
                                <option value="high">{{ __('High') }}</option>
                                <option value="urgent">{{ __('Urgent') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Date') }}</label>
                            <input type="date" name="scheduled_at" class="input">
                        </div>
                        <div style="grid-column: span 3;">
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Issue') }} *</label>
                            <input type="text" name="title" class="input" required maxlength="200" placeholder="{{ __('e.g. Aircond in Room 2 not cooling') }}">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Repair cost (RM)') }}</label>
                            <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01" placeholder="{{ __('optional') }}">
                        </div>
                        <div style="grid-column: span 4;">
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Description') }}</label>
                            <textarea name="description" class="input" maxlength="2000" rows="2"
                                      placeholder="{{ __('Optional detail — press Enter for a new line') }}"
                                      x-data
                                      x-init="$nextTick(() => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px' })"
                                      @input="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                      @focus="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                      style="resize:none; overflow:hidden; min-height:40px; line-height:1.45;"></textarea>
                        </div>
                        <div style="grid-column: span 4; text-align: right;">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Create ticket') }}</button>
                        </div>
                    </form>
                </details>
                <div class="hk-form-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                    @foreach ([
                        [__('Open'), $maintenanceStats['open'], __('awaiting triage'), 'var(--warn)'],
                        [__('In progress'), $maintenanceStats['in_progress'], __('being repaired'), 'var(--accent)'],
                        [__('High priority'), $maintenanceStats['high_priority'], __('needs attention'), 'var(--err)'],
                        [__('Resolved (30d)'), $maintenanceStats['resolved_30d'], __('completed in last 30d'), 'var(--ok)'],
                    ] as [$label, $value, $sub, $tone])
                        <div class="hauz-card" style="padding: 14px;">
                            <div class="kicker" style="margin-bottom: 6px;">{{ $label }}</div>
                            <div style="font-size: 24px; font-weight: 600; line-height: 1; color: {{ $tone ?? 'var(--ink)' }};">{{ $value }}</div>
                            <div style="margin-top: 4px; font-size: 11px; color: var(--ink-3);">{{ $sub }}</div>
                        </div>
                    @endforeach
                </div>

                <div>
                    <div style="font-size: 14px; font-weight: 600;">{{ __('Tickets') }}</div>
                    <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('Open, in-progress and recently resolved (30 days) — with recorded repair cost') }}</div>
                    <div class="hauz-card hk-wrap" style="padding: 0; margin-top: 10px;">
                        <table class="hk-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Issue') }}</th>
                                    <th>{{ __('Priority') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="hk-num">{{ __('Cost') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            @forelse ($maintenance as $m)
                                @php
                                    $ui = $maintenanceStatusUI[$m->status] ?? $maintenanceStatusUI['open'];
                                    $pc = $maintenancePriorityColor[$m->priority] ?? 'var(--ink-3)';
                                @endphp
                                <tbody x-data="{ editing: false }">
                                    <tr x-show="!editing">
                                        <td class="hk-title">
                                            <div style="display:flex; align-items:stretch; gap: 10px;">
                                                <span style="width: 4px; min-height: 30px; background: {{ $pc }}; border-radius: 3px; flex-shrink: 0;"></span>
                                                <div style="min-width: 0;">
                                                    <div style="font-weight: 500; font-size: 13px;">{{ $m->title }}</div>
                                                    <div class="hk-ref">{{ $m->property?->name ?? '—' }}@if ($m->room) · {{ $m->room->name }}@endif · @if ($m->scheduled_at)📅 {{ $m->scheduled_at->format('M j, Y') }}@else{{ $m->created_at->diffForHumans() }}@endif@if ($m->reportedBy) · {{ __('Reported by') }} {{ $m->reportedBy->name }}@endif</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="{{ __('Priority') }}">
                                            <span class="pill" style="background: color-mix(in srgb, {{ $pc }} 14%, transparent); color: {{ $pc }}; height: 18px; font-size: 10.5px;">
                                                <span class="pill-dot" style="background: {{ $pc }};"></span>{{ ucfirst((string) $m->priority) }}
                                            </span>
                                        </td>
                                        <td data-label="{{ __('Status') }}">
                                            <span class="pill" style="background: {{ $ui['bg'] }}; color: {{ $ui['color'] }}; height: 18px; font-size: 10.5px;">
                                                <span class="pill-dot" style="background: {{ $ui['color'] }};"></span>{{ $ui['label'] }}
                                            </span>
                                        </td>
                                        <td class="hk-num hk-cost" data-label="{{ __('Cost') }}">{{ $m->cost !== null ? 'RM '.number_format($m->cost, 2) : '—' }}</td>
                                        <td>
                                            <div class="hk-actions">
                                                <x-housekeeping.share-button :url="$maintenanceShare[$m->id] ?? '#'"/>
                                                <x-housekeeping.copy-button :text="$maintenanceCopy[$m->id] ?? ''"/>
                                                @if ($m->status === 'open')
                                                    <form method="POST" action="{{ route('tenant.housekeeping.maintenance.update', $m->id) }}">
                                                        @csrf @method('PATCH')<input type="hidden" name="action" value="start">
                                                        <button type="submit" class="btn btn-sm">{{ __('Start') }}</button>
                                                    </form>
                                                @elseif ($m->status === 'in_progress')
                                                    <form method="POST" action="{{ route('tenant.housekeeping.maintenance.update', $m->id) }}"
                                                          style="display:flex; gap:4px; align-items:center;">
                                                        @csrf @method('PATCH')<input type="hidden" name="action" value="resolve">
                                                        <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01"
                                                               placeholder="{{ __('Cost RM') }}" title="{{ __('Repair cost (RM)') }}"
                                                               style="width:88px; padding:6px 8px; font-size:12px;">
                                                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Resolve') }}</button>
                                                    </form>
                                                @endif
                                                <button type="button" class="btn btn-sm" @click="editing = true">{{ __('Edit') }}</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr x-show="editing" x-cloak>
                                        <td colspan="5" style="background: var(--bg-sunk); padding: 16px 14px;">
                                            <div style="font-weight: 600; font-size: 13px; margin-bottom: 12px;">{{ __('Edit maintenance ticket') }} · <span class="mono" style="color: var(--ink-3);">MT-{{ str_pad($m->id, 4, '0', STR_PAD_LEFT) }}</span></div>
                                            <form method="POST" action="{{ route('tenant.housekeeping.maintenance.update', $m->id) }}" class="hk-form-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="action" value="edit">
                                                <div style="grid-column: span 2;">
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Property') }} *</label>
                                                    <select name="property_id" class="input" required>
                                                        @foreach ($properties as $p)
                                                            <option value="{{ $p->id }}" @selected($m->property_id == $p->id)>{{ $p->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Priority') }} *</label>
                                                    <select name="priority" class="input" required>
                                                        @foreach (['low' => __('Low'), 'medium' => __('Medium'), 'high' => __('High'), 'urgent' => __('Urgent')] as $val => $lbl)
                                                            <option value="{{ $val }}" @selected($m->priority === $val)>{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Status') }} *</label>
                                                    <select name="status" class="input" required>
                                                        @foreach (['open' => __('Open'), 'in_progress' => __('In progress'), 'resolved' => __('Resolved'), 'closed' => __('Closed')] as $val => $lbl)
                                                            <option value="{{ $val }}" @selected($m->status === $val)>{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Date') }}</label>
                                                    <input type="date" name="scheduled_at" class="input" value="{{ $m->scheduled_at?->format('Y-m-d') }}">
                                                </div>
                                                <div style="grid-column: span 2;">
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Issue') }} *</label>
                                                    <input type="text" name="title" class="input" required maxlength="200" value="{{ $m->title }}">
                                                </div>
                                                <div>
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Repair cost (RM)') }}</label>
                                                    <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01" value="{{ $m->cost }}" placeholder="{{ __('optional') }}">
                                                </div>
                                                <div style="grid-column: span 4;">
                                                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Description') }}</label>
                                                    <textarea name="description" class="input" maxlength="2000" rows="2"
                                                              placeholder="{{ __('Optional detail — press Enter for a new line') }}"
                                                              x-init="$nextTick(() => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px' })"
                                                              @input="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                                              @focus="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                                                              style="resize:none; overflow:hidden; min-height:40px; line-height:1.45;">{{ $m->description }}</textarea>
                                                </div>
                                                <div style="grid-column: span 4; display:flex; justify-content:space-between; gap: 8px; align-items:center;">
                                                    <button type="submit" form="del-maintenance-{{ $m->id }}" class="btn btn-sm" style="color: var(--err);">{{ __('Delete ticket') }}</button>
                                                    <div style="display:flex; gap: 8px;">
                                                        <button type="button" class="btn btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                                                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                                                    </div>
                                                </div>
                                            </form>
                                            <form method="POST" id="del-maintenance-{{ $m->id }}" action="{{ route('tenant.housekeeping.maintenance.destroy', $m->id) }}" onsubmit="return confirm('{{ __('Delete this maintenance ticket?') }}')">
                                                @csrf @method('DELETE')
                                            </form>
                                        </td>
                                    </tr>
                                </tbody>
                            @empty
                                <tbody><tr><td colspan="5" class="hk-empty">{{ __('No maintenance tickets.') }}</td></tr></tbody>
                            @endforelse
                            @if ($maintenance->isNotEmpty())
                                <tfoot class="hk-tfoot"><tr>
                                    <td colspan="3">{{ $maintenance->count() }} {{ trans_choice('ticket|tickets', $maintenance->count()) }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format((float) $maintenance->sum('cost'), 2) }}</td>
                                    <td></td>
                                </tr></tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- History & costs tab --}}
        @if ($tab === 'history')
            <div style="display:flex; flex-direction:column; gap: 20px;">
                {{-- Summary cards --}}
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    @foreach ([
                        [__('This month').' · '.$history['this_month_label'], $history['this_month'], 'var(--primary)'],
                        [__('All-time total'), $history['grand_total'], 'var(--ink)'],
                        [__('Cleaning (all-time)'), $history['cleaning_total'], 'var(--ok)'],
                        [__('Laundry (all-time)'), $history['laundry_total'], 'var(--info)'],
                        [__('Expenses (all-time)'), $history['expenses_total'], 'var(--accent)'],
                    ] as [$label, $amount, $color])
                        <div class="hauz-card" style="padding: 16px;">
                            <div style="font-size: 11.5px; color: var(--ink-3); text-transform:uppercase; letter-spacing:.4px;">{{ $label }}</div>
                            <div style="font-size: 22px; font-weight: 700; margin-top: 6px; color: {{ $color }}; font-variant-numeric:tabular-nums;">RM {{ number_format((float) $amount, 2) }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Monthly breakdown --}}
                <div>
                    <div style="font-size: 14px; font-weight: 600;">{{ __('Monthly cost') }}</div>
                    <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('Cleaning by clean date · laundry by pickup date · maintenance by resolved date. Tap a month for details.') }}</div>
                    <div class="hauz-card hk-wrap" style="padding: 0; margin-top: 10px;">
                        <table class="hk-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Month') }}</th>
                                    <th class="hk-num">{{ __('Cleaning') }}</th>
                                    <th class="hk-num">{{ __('Laundry') }}</th>
                                    <th class="hk-num">{{ __('Maintenance') }}</th>
                                    <th class="hk-num">{{ __('Expenses') }}</th>
                                    <th class="hk-num">{{ __('Total') }}</th>
                                    <th class="hk-num">{{ __('Cumulative') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($history['rows'] as $r)
                                    <tr style="{{ $selectedMonth === $r['key'] ? 'background: var(--primary-tint);' : '' }}">
                                        <td class="hk-title"><a href="{{ route('tenant.housekeeping.index', ['tab' => 'history', 'month' => $r['key']]) }}" style="color: var(--primary); font-weight:600; text-decoration:none;">{{ $r['label'] }}</a></td>
                                        <td class="hk-num hk-cost" data-label="{{ __('Cleaning') }}">{{ $r['cleaning'] ? 'RM '.number_format($r['cleaning'], 2) : '—' }}</td>
                                        <td class="hk-num hk-cost" data-label="{{ __('Laundry') }}">{{ $r['laundry'] ? 'RM '.number_format($r['laundry'], 2) : '—' }}</td>
                                        <td class="hk-num hk-cost" data-label="{{ __('Maintenance') }}">{{ $r['maintenance'] ? 'RM '.number_format($r['maintenance'], 2) : '—' }}</td>
                                        <td class="hk-num hk-cost" data-label="{{ __('Expenses') }}">{{ $r['expenses'] ? 'RM '.number_format($r['expenses'], 2) : '—' }}</td>
                                        <td class="hk-num hk-cost" data-label="{{ __('Total') }}">RM {{ number_format($r['total'], 2) }}</td>
                                        <td class="hk-num hk-cost" data-label="{{ __('Cumulative') }}" style="color: var(--ink-3);">RM {{ number_format($r['cumulative'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="hk-empty">{{ __('No costs recorded yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                            @if (! empty($history['rows']))
                                <tfoot class="hk-tfoot"><tr>
                                    <td class="hk-title">{{ __('All-time') }}</td>
                                    <td class="hk-num hk-cost" data-label="{{ __('Cleaning') }}">RM {{ number_format($history['cleaning_total'], 2) }}</td>
                                    <td class="hk-num hk-cost" data-label="{{ __('Laundry') }}">RM {{ number_format($history['laundry_total'], 2) }}</td>
                                    <td class="hk-num hk-cost" data-label="{{ __('Maintenance') }}">RM {{ number_format($history['maintenance_total'], 2) }}</td>
                                    <td class="hk-num hk-cost" data-label="{{ __('Expenses') }}">RM {{ number_format($history['expenses_total'], 2) }}</td>
                                    <td class="hk-num hk-cost" data-label="{{ __('Total') }}">RM {{ number_format($history['grand_total'], 2) }}</td>
                                    <td class="hk-hide-mobile"></td>
                                </tr></tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- Selected month drill-down --}}
                @if ($monthDetail)
                    <div>
                        <div style="display:flex; align-items:baseline; gap: 8px;">
                            <div style="font-size: 14px; font-weight: 600;">{{ $monthDetail['label'] }}</div>
                            <div style="font-size: 12px; color: var(--ink-3);">{{ count($monthDetail['items']) }} {{ trans_choice('item|items', count($monthDetail['items'])) }}</div>
                        </div>
                        <div class="hauz-card hk-wrap" style="padding: 0; margin-top: 10px;">
                            <table class="hk-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Category') }}</th>
                                        <th>{{ __('Detail') }}</th>
                                        <th>{{ __('Property') }}</th>
                                        <th>{{ __('Who') }}</th>
                                        <th class="hk-num">{{ __('Cost') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $catLabel = ['cleaning' => __('Cleaning'), 'laundry' => __('Laundry'), 'maintenance' => __('Maintenance'), 'expenses' => __('Expense')]; @endphp
                                    @foreach ($monthDetail['items'] as $it)
                                        <tr>
                                            <td class="hk-title" style="white-space:nowrap;">{{ $it['date']?->format('j M Y') }}</td>
                                            <td data-label="{{ __('Category') }}">{{ $catLabel[$it['cat']] ?? $it['cat'] }}</td>
                                            <td data-label="{{ __('Detail') }}" style="text-transform:capitalize;">{{ str_replace('_', ' ', (string) $it['type']) }}</td>
                                            <td data-label="{{ __('Property') }}">{{ $it['property'] ?? '—' }}</td>
                                            <td data-label="{{ __('Who') }}">{{ $it['who'] ?? '—' }}</td>
                                            <td class="hk-num hk-cost" data-label="{{ __('Cost') }}">RM {{ number_format($it['cost'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="hk-tfoot"><tr>
                                    <td colspan="5" class="hk-title">{{ __('Month total') }}</td>
                                    <td class="hk-num hk-cost" data-label="{{ __('Total') }}">RM {{ number_format(collect($monthDetail['items'])->sum('cost'), 2) }}</td>
                                </tr></tfoot>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
