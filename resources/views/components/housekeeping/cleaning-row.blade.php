@props([
    'task',
    'properties',
    'cleaners',
    'copyText' => '',
    'shareUrl' => null,
])
@php
    $t = $task;
    $statusUI = [
        'pending'     => ['bg' => 'var(--bg-sunk)',     'color' => 'var(--ink-2)',  'label' => __('Scheduled')],
        'in_progress' => ['bg' => 'var(--accent-tint)', 'color' => 'var(--accent)', 'label' => __('In progress')],
        'completed'   => ['bg' => 'var(--ok-tint)',     'color' => 'var(--ok)',     'label' => __('Done')],
        'skipped'     => ['bg' => 'var(--err-tint)',    'color' => 'var(--err)',    'label' => __('Skipped')],
    ];
    $ui = $statusUI[$t->status] ?? $statusUI['pending'];
    $hasIssues = ! empty($t->issues);
    $typeLabels = [
        'full' => __('Full turnover'),
        'light' => __('Light refresh'),
        'deep' => __('Deep clean'),
        'pool' => __('Pool / outdoor'),
        'post_event' => __('Post-event'),
        'pre_arrival' => __('Pre-arrival dusting'),
    ];
    $typeLabel = $typeLabels[$t->type] ?? ucfirst(str_replace('_', ' ', (string) $t->type));
    $durationHours = $t->duration_minutes ? rtrim(rtrim(number_format($t->duration_minutes / 60, 1), '0'), '.') : null;
    $crewBits = [];
    if ($t->cleaners_required) { $crewBits[] = '👥 '.(int) $t->cleaners_required; }
    if ($durationHours) { $crewBits[] = '⏱ '.$durationHours.'h'; }
    $crewLabel = implode(' · ', $crewBits);
@endphp
<tbody x-data="{ editing: false }">
    {{-- Display row --}}
    <tr x-show="!editing">
        {{-- `hk-title` = the row's heading on mobile (no data-label); the rest of
             the cells carry their column name so the stacked card reads clearly. --}}
        <td class="hk-title">
            <div style="font-weight: 600; font-size: 13px;">{{ $t->property?->name ?? '—' }}</div>
            <div class="hk-ref mono">CL-{{ str_pad($t->id, 4, '0', STR_PAD_LEFT) }}@if ($t->auto_generated) · <span style="color: var(--primary);">{{ __('Auto') }}</span>@endif@if ($hasIssues) · <span style="color: var(--err);">{{ __('Flagged') }}</span>@endif</div>
        </td>
        <td data-label="{{ __('Type') }}">
            {{ $typeLabel }}
            @if ($t->room)<div class="hk-ref">{{ $t->room->name }}</div>@endif
        </td>
        <td class="mono" data-label="{{ __('Scheduled') }}" style="white-space: nowrap;">
            {{ $t->scheduled_at?->format('D, j M') }}
            <div class="hk-ref mono">{{ $t->scheduled_at?->format('H:i') }}</div>
        </td>
        <td data-label="{{ __('Cleaner') }}">
            @if ($t->cleaner)
                <div style="display:flex; align-items:center; gap: 7px;">
                    <x-avatar :name="$t->cleaner->name" :size="24"/>
                    <div style="min-width:0;">
                        <div style="font-weight: 500;">{{ $t->cleaner->name }}</div>
                        @if ($t->cleaner->phone)<div class="hk-ref mono">{{ $t->cleaner->phone }}</div>@endif
                    </div>
                </div>
            @else
                <span style="color: var(--ink-3);">{{ __('Unassigned') }}</span>
            @endif
        </td>
        <td data-label="{{ __('Crew') }}" style="white-space: nowrap;">{{ $crewLabel !== '' ? $crewLabel : '—' }}</td>
        <td data-label="{{ __('Status') }}">
            <span class="pill" style="background: {{ $ui['bg'] }}; color: {{ $ui['color'] }}; height: 18px; font-size: 10.5px;">
                <span class="pill-dot" style="background: {{ $ui['color'] }};"></span>{{ $ui['label'] }}
            </span>
        </td>
        <td class="hk-num hk-cost" data-label="{{ __('Cost') }}">{{ $t->cost !== null ? 'RM '.number_format($t->cost, 2) : '—' }}</td>
        <td>
            <div class="hk-actions">
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
                @if ($shareUrl)
                    <x-housekeeping.share-button :url="$shareUrl"/>
                @endif
                <x-housekeeping.copy-button :text="$copyText"/>
                <button type="button" class="btn btn-sm" @click="editing = true">{{ __('Edit') }}</button>
            </div>
        </td>
    </tr>

    {{-- Edit row --}}
    <tr x-show="editing" x-cloak>
        <td colspan="8" style="background: var(--bg-sunk); padding: 16px 14px;">
            <div style="font-weight: 600; font-size: 13px; margin-bottom: 12px;">{{ __('Edit cleaning task') }} · <span class="mono" style="color: var(--ink-3);">CL-{{ str_pad($t->id, 4, '0', STR_PAD_LEFT) }}</span></div>
            <form method="POST" action="{{ route('tenant.housekeeping.cleaning.update', $t->id) }}" class="hk-form-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="edit">
                <div style="grid-column: span 2;">
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Property') }} *</label>
                    <select name="property_id" class="input" required>
                        @foreach ($properties as $p)
                            <option value="{{ $p->id }}" @selected($t->property_id == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Type') }} *</label>
                    <select name="type" class="input" required>
                        @foreach ($typeLabels as $val => $lbl)
                            <option value="{{ $val }}" @selected($t->type === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Status') }} *</label>
                    <select name="status" class="input" required>
                        @foreach (['pending' => __('Scheduled'), 'in_progress' => __('In progress'), 'completed' => __('Done'), 'skipped' => __('Skipped')] as $val => $lbl)
                            <option value="{{ $val }}" @selected($t->status === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Scheduled at') }} *</label>
                    <input type="datetime-local" name="scheduled_at" class="input" required value="{{ $t->scheduled_at?->format('Y-m-d\TH:i') }}">
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cleaner') }}</label>
                    <select name="cleaner_id" class="input">
                        <option value="">{{ __('— Unassigned —') }}</option>
                        @foreach ($cleaners as $cl)
                            <option value="{{ $cl->id }}" @selected($t->cleaner_id == $cl->id)>{{ $cl->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cleaners needed') }}</label>
                    <input type="number" name="cleaners_required" class="input" min="1" max="20" step="1" value="{{ $t->cleaners_required ?? 1 }}">
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Duration (hours)') }}</label>
                    <input type="number" name="duration_hours" class="input" min="0.5" max="24" step="0.5" value="{{ $durationHours }}" placeholder="e.g. 2">
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Cost (RM)') }}</label>
                    <input type="number" name="cost" class="input" min="0" max="1000000" step="0.01" value="{{ $t->cost }}" placeholder="0.00">
                </div>
                <div style="grid-column: span 4;">
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Notes') }}</label>
                    <textarea name="notes" class="input" maxlength="2000" rows="2"
                              placeholder="{{ __('Press Enter for a new line, e.g. 1. … 2. … 3. …') }}"
                              x-init="$nextTick(() => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px' })"
                              @input="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                              @focus="$el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'"
                              style="resize:none; overflow:hidden; min-height:40px; line-height:1.45;">{{ $t->notes }}</textarea>
                </div>
                <div style="grid-column: span 4; display:flex; justify-content:space-between; gap: 8px; align-items:center;">
                    <button type="submit" form="del-clean-{{ $t->id }}" class="btn btn-sm" style="color: var(--err);">{{ __('Delete task') }}</button>
                    <div style="display:flex; gap: 8px;">
                        <button type="button" class="btn btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                    </div>
                </div>
            </form>
            <form method="POST" id="del-clean-{{ $t->id }}" action="{{ route('tenant.housekeeping.cleaning.destroy', $t->id) }}" onsubmit="return confirm('{{ __('Delete this cleaning task?') }}')">
                @csrf @method('DELETE')
            </form>
        </td>
    </tr>
</tbody>
