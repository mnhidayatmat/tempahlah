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
                <a href="{{ route('tenant.housekeeping.print') }}" target="_blank" rel="noopener" class="btn btn-sm">{{ __("Print today's run sheet") }}</a>
            </div>
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
            ] as $t)
                @php $active = $tab === $t['key']; @endphp
                <a href="{{ route('tenant.housekeeping.index', ['tab' => $t['key']]) }}" style="
                    padding: 10px 16px; border: 0; background: transparent;
                    text-decoration: none; font-size: 13px; font-weight: 500;
                    color: {{ $active ? 'var(--ink)' : 'var(--ink-3)' }};
                    border-bottom: 2px solid {{ $active ? 'var(--primary)' : 'transparent' }};
                    margin-bottom: -1px; display:inline-flex; align-items:center; gap: 6px;">
                    {{ $t['label'] }}
                    <span style="background: {{ $active ? 'var(--primary-tint)' : 'var(--bg-sunk)' }};
                                 color: {{ $active ? 'var(--primary)' : 'var(--ink-3)' }};
                                 padding: 1px 6px; border-radius: 999px; font-size: 10.5px; font-weight: 600;">
                        {{ $t['count'] }}
                    </span>
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

                {{-- Copy-paste WhatsApp cleaning schedule --}}
                <x-housekeeping.schedule-card
                    tab="cleaning"
                    :title="__('Cleaning schedule for WhatsApp')"
                    :subtitle="__('Copy & paste into your cleaner group')"
                    :schedule-date="$scheduleDate"
                    :text="$cleaningSchedule"
                    refName="clsched"/>

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
                            </select>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Scheduled at') }} *</label>
                            <input type="datetime-local" name="scheduled_at" class="input" required x-ref="sched">
                            <div style="font-size: 10.5px; color: var(--ink-3); margin-top: 3px;">{{ __('Auto-set to ~1h after check-out') }}</div>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cost (RM)') }}</label>
                            <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01" placeholder="0.00">
                        </div>
                        <div style="grid-column: span 3;">
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Notes') }}</label>
                            <input type="text" name="notes" class="input" maxlength="500" placeholder="{{ __('Optional handoff notes for the cleaner') }}">
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
                    <div style="display:flex; flex-direction:column; gap: 10px; margin-top: 10px;">
                        @forelse ($todayTasks as $t)
                            @php
                                $ui = $cleaningStatusUI[$t->status] ?? $cleaningStatusUI['pending'];
                                $hasIssues = !empty($t->issues);
                                $borderColor = $hasIssues ? 'var(--err)' : ($t->type === 'deep' ? 'var(--accent)' : 'var(--ink-3)');
                            @endphp
                            <div class="hauz-card" style="padding: 16px; display:grid; grid-template-columns: auto 1fr auto; gap: 16px; align-items:center; border-left: 3px solid {{ $borderColor }};">
                                <div style="width: 44px; height: 44px; border-radius: 10px; background: var(--bg-sunk); display:flex; align-items:center; justify-content:center; color: var(--ink-2);">
                                    <x-icon :name="$t->type === 'deep' ? 'sparkle' : 'bed'" :size="20"/>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="display:flex; align-items:center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                                        <span style="font-weight: 600; font-size: 14px;">{{ $t->property?->name ?? '—' }}</span>
                                        <span class="mono" style="font-size: 11px; color: var(--ink-3);">CL-{{ str_pad($t->id, 4, '0', STR_PAD_LEFT) }}</span>
                                        <span class="pill" style="background: {{ $ui['bg'] }}; color: {{ $ui['color'] }}; height: 18px; font-size: 10.5px;">
                                            <span class="pill-dot" style="background: {{ $ui['color'] }};"></span>{{ $ui['label'] }}
                                        </span>
                                    </div>
                                    <div style="font-size: 12.5px; color: var(--ink-2); display:flex; gap: 14px; flex-wrap: wrap;">
                                        <span>{{ ucfirst((string) $t->type) }} {{ __('clean') }}</span>
                                        <span class="mono">{{ $t->scheduled_at->format('H:i') }}</span>
                                        @if ($t->room)
                                            <span>{{ $t->room->name }}</span>
                                        @endif
                                        @if ($t->booking)
                                            <span style="color: var(--ink-3);">
                                                <x-icon name="arrow-right" :size="11" style="vertical-align: middle; margin-right: 2px;"/>
                                                {{ $t->booking->guest?->name ?? __('Guest') }} {{ __('arrives after') }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($t->notes)
                                        <div style="font-size: 11.5px; color: {{ $hasIssues ? 'var(--err)' : 'var(--ink-3)' }}; margin-top: 6px; font-style: italic;">"{{ $t->notes }}"</div>
                                    @endif
                                </div>
                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap: 8px;">
                                    @if ($t->assignee)
                                        <div style="display:flex; align-items:center; gap: 8px;">
                                            <x-avatar :name="$t->assignee->name" :size="26"/>
                                            <div style="font-size: 12;">
                                                <div style="font-weight: 500;">{{ explode(' ', $t->assignee->name)[0] }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <span style="font-size: 11px; color: var(--ink-3);">{{ __('Unassigned') }}</span>
                                    @endif
                                    <div style="display:flex; gap: 4px;">
                                        @if ($t->status === 'pending')
                                            <form method="POST" action="{{ route('tenant.housekeeping.cleaning.update', $t->id) }}">
                                                @csrf @method('PATCH')<input type="hidden" name="action" value="start">
                                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Start') }}</button>
                                            </form>
                                        @elseif ($t->status === 'in_progress')
                                            <form method="POST" action="{{ route('tenant.housekeeping.cleaning.update', $t->id) }}">
                                                @csrf @method('PATCH')<input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Complete') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="hauz-card" style="padding: 32px; text-align: center; color: var(--ink-3); font-size: 13px;">
                                {{ __('No cleaning tasks scheduled today.') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Upcoming --}}
                @if ($upcoming->isNotEmpty())
                    <div>
                        <div style="font-size: 14px; font-weight: 600;">{{ __('Upcoming · next 7 days') }}</div>
                        <div class="hauz-card" style="padding: 0; overflow: hidden; margin-top: 10px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr style="background: var(--bg-sunk);">
                                        @foreach ([__('Date'), __('Property'), __('Type'), __('Time'), __('Assignee'), __('Booking')] as $h)
                                            <th style="text-align: left; padding: 10px 14px; font-weight: 500; font-size: 11px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .08em;">{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($upcoming as $t)
                                        <tr style="border-top: .5px solid var(--line);">
                                            <td style="padding: 12px 14px;" class="mono">{{ $t->scheduled_at->format('M j') }}</td>
                                            <td style="padding: 12px 14px; font-weight: 500;">{{ $t->property?->name ?? '—' }}</td>
                                            <td style="padding: 12px 14px; color: var(--ink-2);">{{ ucfirst((string) $t->type) }}</td>
                                            <td style="padding: 12px 14px; color: var(--ink-2);" class="mono">{{ $t->scheduled_at->format('H:i') }}</td>
                                            <td style="padding: 12px 14px;">
                                                @if ($t->assignee)
                                                    <span style="font-size: 12.5px;">{{ $t->assignee->name }}</span>
                                                @else
                                                    <span style="color: var(--ink-3);">{{ __('Unassigned') }}</span>
                                                @endif
                                            </td>
                                            <td style="padding: 12px 14px; font-size: 12; color: var(--ink-3);">
                                                {{ $t->booking?->reference ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
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

                {{-- Copy-paste WhatsApp laundry schedule --}}
                <x-housekeeping.schedule-card
                    tab="laundry"
                    :title="__('Laundry schedule for WhatsApp')"
                    :subtitle="__('Copy & paste into your laundry / dobi group')"
                    :schedule-date="$scheduleDate"
                    :text="$laundrySchedule"
                    refName="ldsched"/>

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
                            <input type="text" name="vendor_name" class="input" maxlength="120" placeholder="{{ __('e.g. Dobi Mesra') }}">
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
                            <input type="text" name="notes" class="input" maxlength="500">
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
                    <div class="hauz-card" style="padding: 0; overflow: hidden; margin-top: 10px;">
                        @forelse ($laundry as $i => $l)
                            @php $ui = $laundryStatusUI[$l->status] ?? $laundryStatusUI['pending']; @endphp
                            <div style="padding: 14px 18px; {{ $i === 0 ? '' : 'border-top: .5px solid var(--line);' }} display:grid; grid-template-columns: 1fr 2fr 1.4fr 1fr auto; gap: 16px; align-items:center;">
                                <div>
                                    <div style="font-size: 13px; font-weight: 500;">{{ $l->property?->name ?? '—' }}</div>
                                    <div class="mono" style="font-size: 11px; color: var(--ink-3);">LD-{{ str_pad($l->id, 4, '0', STR_PAD_LEFT) }}</div>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 12.5px; color: var(--ink-2);">{{ $l->item_count }} {{ __('items') }}</div>
                                    <div style="font-size: 11px; color: var(--ink-3); margin-top: 2px;">{{ $l->vendor_name ?? __('Self-service') }}</div>
                                </div>
                                <div>
                                    <span class="pill" style="background: {{ $ui['bg'] }}; color: {{ $ui['color'] }}; height: 18px; font-size: 10.5px;">
                                        <span class="pill-dot" style="background: {{ $ui['color'] }};"></span>{{ $ui['label'] }}
                                    </span>
                                </div>
                                <div style="font-size: 12;">
                                    @if ($l->picked_up_at)
                                        <div style="color: var(--ink-2);">{{ __('Picked') }}: <span class="mono">{{ $l->picked_up_at->format('M j') }}</span></div>
                                    @endif
                                    @if ($l->expected_return_at)
                                        <div style="color: {{ $l->returned_at ? 'var(--ok)' : 'var(--ink-3)' }};">
                                            {{ $l->returned_at ? __('Returned') : __('Expected') }}: <span class="mono">{{ ($l->returned_at ?? $l->expected_return_at)->format('M j') }}</span>
                                        </div>
                                    @endif
                                    @if (! $l->picked_up_at)
                                        <div style="color: var(--warn);">{{ __('Awaiting pickup') }}</div>
                                    @endif
                                </div>
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
                                @else
                                    <span style="font-size: 11px; color: var(--ink-3);">{{ __('Done') }}</span>
                                @endif
                            </div>
                        @empty
                            <div style="padding: 32px; text-align: center; color: var(--ink-3); font-size: 13px;">
                                {{ __('No laundry batches in the last 14 days.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- Maintenance tab --}}
        @if ($tab === 'maintenance')
            <div style="display:flex; flex-direction:column; gap: 18px;">
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

                <div class="hauz-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 14px 18px; border-bottom: .5px solid var(--line);">
                        <div style="font-weight: 600; font-size: 14px;">{{ __('Active tickets') }}</div>
                        <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">{{ __('Open and in-progress maintenance issues across your properties') }}</div>
                    </div>
                    @forelse ($maintenance as $m)
                        @php $ui = $maintenanceStatusUI[$m->status] ?? $maintenanceStatusUI['open']; @endphp
                        <div style="padding: 14px 18px; border-top: .5px solid var(--line); display:grid; grid-template-columns: auto 1fr auto auto; gap: 14px; align-items:center;">
                            <div style="width: 8px; height: 36px; background: {{ $maintenancePriorityColor[$m->priority] ?? 'var(--ink-3)' }}; border-radius: 4px;" title="{{ ucfirst($m->priority) }} priority"></div>
                            <div style="min-width: 0;">
                                <div style="font-weight: 500; font-size: 13.5px;">{{ $m->title }}</div>
                                <div style="font-size: 12px; color: var(--ink-3); margin-top: 2px;">
                                    {{ $m->property?->name ?? '—' }}
                                    @if ($m->room) · {{ $m->room->name }} @endif
                                    · {{ $m->created_at->diffForHumans() }}
                                    @if ($m->reportedBy) · {{ __('Reported by') }} {{ $m->reportedBy->name }} @endif
                                </div>
                            </div>
                            <span class="pill" style="background: {{ $ui['bg'] }}; color: {{ $ui['color'] }}; height: 18px; font-size: 10.5px;">
                                <span class="pill-dot" style="background: {{ $ui['color'] }};"></span>{{ $ui['label'] }}
                            </span>
                            <div style="display:flex; gap: 4px;">
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
                                               style="width:96px; padding:6px 8px; font-size:12px;">
                                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Resolve') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div style="padding: 32px; text-align: center; color: var(--ink-3); font-size: 13px;">
                            {{ __('No active maintenance tickets.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
