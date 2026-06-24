@props([
    'task',
    'properties',
    'cleaners',
    'copyText' => '',
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
    $borderColor = $hasIssues ? 'var(--err)' : ($t->type === 'deep' ? 'var(--accent)' : 'var(--ink-3)');
@endphp
<div class="hauz-card" x-data="{ editing: false }" style="padding: 16px; border-left: 3px solid {{ $borderColor }};">
    {{-- Display row --}}
    <div x-show="!editing" style="display:grid; grid-template-columns: auto 1fr auto; gap: 16px; align-items:center;">
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
                <span class="mono">{{ $t->scheduled_at?->format('M j · H:i') }}</span>
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
                <div style="font-size: 11.5px; color: {{ $hasIssues ? 'var(--err)' : 'var(--ink-3)' }}; margin-top: 6px; font-style: italic; white-space: pre-line;">"{{ $t->notes }}"</div>
            @endif
        </div>
        <div style="display:flex; flex-direction:column; align-items:flex-end; gap: 8px;">
            @if ($t->cleaner)
                <div style="display:flex; align-items:center; gap: 8px;">
                    <x-avatar :name="$t->cleaner->name" :size="26"/>
                    <div style="font-size: 12px; text-align:right;">
                        <div style="font-weight: 500;">{{ $t->cleaner->name }}</div>
                        @if ($t->cleaner->phone)
                            <div class="mono" style="font-size: 10.5px; color: var(--ink-3);">{{ $t->cleaner->phone }}</div>
                        @endif
                    </div>
                </div>
            @else
                <span style="font-size: 11px; color: var(--ink-3);">{{ __('No cleaner assigned') }}</span>
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
                <x-housekeeping.copy-button :text="$copyText"/>
                <button type="button" class="btn btn-sm" @click="editing = true">{{ __('Edit') }}</button>
            </div>
        </div>
    </div>

    {{-- Edit form --}}
    <div x-show="editing" x-cloak style="display:flex; flex-direction:column; gap: 12px;">
        <div style="font-weight: 600; font-size: 13px;">{{ __('Edit cleaning task') }} · <span class="mono" style="color: var(--ink-3);">CL-{{ str_pad($t->id, 4, '0', STR_PAD_LEFT) }}</span></div>
        <form method="POST" action="{{ route('tenant.housekeeping.cleaning.update', $t->id) }}" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
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
                    @foreach (['full' => __('Full turnover'), 'light' => __('Light refresh'), 'deep' => __('Deep clean'), 'pool' => __('Pool / outdoor'), 'post_event' => __('Post-event')] as $val => $lbl)
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
            <div style="grid-column: span 4; display:flex; justify-content:flex-end; gap: 8px;">
                <button type="button" class="btn btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
            </div>
        </form>
        <form method="POST" action="{{ route('tenant.housekeeping.cleaning.destroy', $t->id) }}" onsubmit="return confirm('{{ __('Delete this cleaning task?') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm" style="color: var(--err);">{{ __('Delete task') }}</button>
        </form>
    </div>
</div>
