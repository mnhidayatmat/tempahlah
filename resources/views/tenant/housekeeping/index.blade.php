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
        .hk-tfoot td{ padding:10px 14px; border-top:.5px solid var(--line); background:var(--bg-sunk); font-size:11.5px; color:var(--ink-3); }
        .hk-tfoot .hk-cost{ font-size:12.5px; }
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
            <div style="display:flex; gap:8px;">
                <form method="POST" action="{{ route('tenant.housekeeping.generate') }}"
                      onsubmit="return confirm('{{ __('Auto-schedule cleaning + laundry for all upcoming confirmed bookings? Existing tasks are kept.') }}')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Generate from bookings') }}</button>
                </form>
                <a href="{{ route('tenant.housekeeping.print') }}" target="_blank" rel="noopener" class="btn btn-sm">{{ __("Print today's run sheet") }}</a>
            </div>
        </div>
        <div style="margin-top:-8px; color: var(--ink-3); font-size: 12px;">
            {{ __('“Generate from bookings” builds the default schedule from your bookings — full clean 30 min after check-out (2 cleaners if the next guest is within 2 days, else 1), pre-arrival dusting when the house sat empty 3+ days, and a laundry batch. Edit or share any task below.') }}
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

        {{-- Tabs --}}
        <div style="display:flex; gap: 2px; border-bottom: .5px solid var(--line);">
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
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
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
                    <div class="hauz-card hk-wrap" style="padding: 0; overflow: hidden; margin-top: 10px;">
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
                        <div class="hauz-card hk-wrap" style="padding: 0; overflow: hidden; margin-top: 10px;">
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
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
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
                    <div class="hauz-card hk-wrap" style="padding: 0; overflow: hidden; margin-top: 10px;">
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
                                        <td>
                                            <div style="font-weight: 500; font-size: 13px;">{{ $l->property?->name ?? '—' }}</div>
                                            <div class="hk-ref mono">LD-{{ str_pad($l->id, 4, '0', STR_PAD_LEFT) }}</div>
                                        </td>
                                        <td style="white-space: nowrap;">{{ $l->item_count }} {{ __('items') }}</td>
                                        <td>{{ $l->vendor?->name ?? $l->vendor_name ?? __('Self-service') }}</td>
                                        <td style="white-space: nowrap;">
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
                                        <td>
                                            <span class="pill" style="background: {{ $ui['bg'] }}; color: {{ $ui['color'] }}; height: 18px; font-size: 10.5px;">
                                                <span class="pill-dot" style="background: {{ $ui['color'] }};"></span>{{ $ui['label'] }}
                                            </span>
                                        </td>
                                        <td class="hk-num hk-cost">{{ $l->cost !== null ? 'RM '.number_format($l->cost, 2) : '—' }}</td>
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
                                            <form method="POST" action="{{ route('tenant.housekeeping.laundry.update', $l->id) }}" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
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
                    <form method="POST" action="{{ route('tenant.housekeeping.maintenance.store') }}"
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
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Repair cost (RM)') }}</label>
                            <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01" placeholder="{{ __('optional') }}">
                        </div>
                        <div style="grid-column: span 4;">
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Issue') }} *</label>
                            <input type="text" name="title" class="input" required maxlength="200" placeholder="{{ __('e.g. Aircond in Room 2 not cooling') }}">
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
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
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
                    <div class="hauz-card hk-wrap" style="padding: 0; overflow: hidden; margin-top: 10px;">
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
                                <tbody>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:stretch; gap: 10px;">
                                                <span style="width: 4px; min-height: 30px; background: {{ $pc }}; border-radius: 3px; flex-shrink: 0;"></span>
                                                <div style="min-width: 0;">
                                                    <div style="font-weight: 500; font-size: 13px;">{{ $m->title }}</div>
                                                    <div class="hk-ref">{{ $m->property?->name ?? '—' }}@if ($m->room) · {{ $m->room->name }}@endif · {{ $m->created_at->diffForHumans() }}@if ($m->reportedBy) · {{ __('Reported by') }} {{ $m->reportedBy->name }}@endif</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="pill" style="background: color-mix(in srgb, {{ $pc }} 14%, transparent); color: {{ $pc }}; height: 18px; font-size: 10.5px;">
                                                <span class="pill-dot" style="background: {{ $pc }};"></span>{{ ucfirst((string) $m->priority) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="pill" style="background: {{ $ui['bg'] }}; color: {{ $ui['color'] }}; height: 18px; font-size: 10.5px;">
                                                <span class="pill-dot" style="background: {{ $ui['color'] }};"></span>{{ $ui['label'] }}
                                            </span>
                                        </td>
                                        <td class="hk-num hk-cost">{{ $m->cost !== null ? 'RM '.number_format($m->cost, 2) : '—' }}</td>
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
                                            </div>
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
                    <div class="hauz-card hk-wrap" style="padding: 0; overflow: hidden; margin-top: 10px;">
                        <table class="hk-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Month') }}</th>
                                    <th class="hk-num">{{ __('Cleaning') }}</th>
                                    <th class="hk-num">{{ __('Laundry') }}</th>
                                    <th class="hk-num">{{ __('Maintenance') }}</th>
                                    <th class="hk-num">{{ __('Total') }}</th>
                                    <th class="hk-num">{{ __('Cumulative') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($history['rows'] as $r)
                                    <tr style="{{ $selectedMonth === $r['key'] ? 'background: var(--primary-tint);' : '' }}">
                                        <td><a href="{{ route('tenant.housekeeping.index', ['tab' => 'history', 'month' => $r['key']]) }}" style="color: var(--primary); font-weight:500; text-decoration:none;">{{ $r['label'] }}</a></td>
                                        <td class="hk-num hk-cost">{{ $r['cleaning'] ? 'RM '.number_format($r['cleaning'], 2) : '—' }}</td>
                                        <td class="hk-num hk-cost">{{ $r['laundry'] ? 'RM '.number_format($r['laundry'], 2) : '—' }}</td>
                                        <td class="hk-num hk-cost">{{ $r['maintenance'] ? 'RM '.number_format($r['maintenance'], 2) : '—' }}</td>
                                        <td class="hk-num hk-cost">RM {{ number_format($r['total'], 2) }}</td>
                                        <td class="hk-num hk-cost" style="color: var(--ink-3);">RM {{ number_format($r['cumulative'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="hk-empty">{{ __('No costs recorded yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                            @if (! empty($history['rows']))
                                <tfoot class="hk-tfoot"><tr>
                                    <td>{{ __('All-time') }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format($history['cleaning_total'], 2) }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format($history['laundry_total'], 2) }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format($history['maintenance_total'], 2) }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format($history['grand_total'], 2) }}</td>
                                    <td></td>
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
                        <div class="hauz-card hk-wrap" style="padding: 0; overflow: hidden; margin-top: 10px;">
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
                                    @php $catLabel = ['cleaning' => __('Cleaning'), 'laundry' => __('Laundry'), 'maintenance' => __('Maintenance')]; @endphp
                                    @foreach ($monthDetail['items'] as $it)
                                        <tr>
                                            <td style="white-space:nowrap;">{{ $it['date']?->format('j M Y') }}</td>
                                            <td>{{ $catLabel[$it['cat']] ?? $it['cat'] }}</td>
                                            <td style="text-transform:capitalize;">{{ str_replace('_', ' ', (string) $it['type']) }}</td>
                                            <td>{{ $it['property'] ?? '—' }}</td>
                                            <td>{{ $it['who'] ?? '—' }}</td>
                                            <td class="hk-num hk-cost">RM {{ number_format($it['cost'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="hk-tfoot"><tr>
                                    <td colspan="5">{{ __('Month total') }}</td>
                                    <td class="hk-num hk-cost">RM {{ number_format(collect($monthDetail['items'])->sum('cost'), 2) }}</td>
                                </tr></tfoot>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
