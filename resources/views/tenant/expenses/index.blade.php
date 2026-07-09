<x-app-layout :title="__('Expenses')">
    @php
        $categories = \App\Models\Expense::CATEGORIES;
        $today = \Illuminate\Support\Carbon::today()->format('Y-m-d');
        // Category accent colours (display only)
        $catColor = [
            'renovation' => 'var(--err)',
            'upgrade'    => 'var(--primary)',
            'furniture'  => 'var(--info)',
            'supplies'   => 'var(--ok)',
            'toilet'     => 'var(--accent)',
            'repair'     => 'var(--warn)',
            'utility'    => 'var(--ink-3)',
            'other'      => 'var(--ink-3)',
        ];
    @endphp

    @once
    <style>
        .ex-wrap{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .ex-table{ width:100%; border-collapse:collapse; font-size:12.5px; min-width:720px; }
        .ex-table thead th{
            text-align:left; padding:9px 14px; font-size:10px; font-weight:600;
            text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3);
            background:var(--bg-sunk); border-bottom:.5px solid var(--line); white-space:nowrap;
        }
        .ex-table tbody td{ padding:12px 14px; border-top:.5px solid var(--line); vertical-align:middle; color:var(--ink-2); }
        .ex-num{ text-align:right; white-space:nowrap; }
        .ex-cost{ font-variant-numeric:tabular-nums; color:var(--ink); font-weight:600; }
        .ex-tag{ display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; padding:2px 9px; border-radius:999px; white-space:nowrap; }
        .ex-tfoot td{ padding:10px 14px; border-top:.5px solid var(--line); background:var(--bg-sunk); font-size:12px; color:var(--ink); }
        .ex-empty{ padding:32px; text-align:center; color:var(--ink-3); font-size:13px; }
        .ex-actions{ display:flex; gap:4px; align-items:center; justify-content:flex-end; }
        .ex-iconbtn{ display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:var(--r-sm); border:.5px solid var(--line); background:var(--bg-elev); color:var(--ink-3); cursor:pointer; }
        .ex-iconbtn:hover{ color:var(--ink); border-color:var(--ink-3); }
        .ex-grid{ display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
        .ex-form-grid{ display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; }
        @media (max-width:720px){ .ex-form-grid{ grid-template-columns: repeat(2, 1fr); } }
        .ex-field label{ display:block; font-size:11px; font-weight:600; color:var(--ink-3); margin-bottom:4px; text-transform:uppercase; letter-spacing:.04em; }
        .ex-field .input, .ex-field select, .ex-field textarea{ width:100%; }
        .ex-chip{ display:inline-flex; align-items:center; gap:6px; padding:5px 11px; border-radius:999px; font-size:12px; font-weight:500; text-decoration:none; border:.5px solid var(--line); background:var(--bg-elev); color:var(--ink-2); white-space:nowrap; }
        .ex-chip.is-active{ background:var(--primary-tint); border-color:var(--primary-edge); color:var(--primary); font-weight:600; }
        .ex-chips{ display:flex; gap:6px; flex-wrap:wrap; }
        [x-cloak]{ display:none !important; }
    </style>
    @endonce

    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Manage') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Expenses') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ __('Track renovation, upgrades, and house supplies. These roll into your monthly operating cost.') }}
                </div>
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

        {{-- Summary cards --}}
        <div class="ex-grid">
            <div class="hauz-card" style="padding: 16px;">
                <div style="font-size: 11.5px; color: var(--ink-3); text-transform:uppercase; letter-spacing:.4px;">{{ __('This month') }} · {{ $thisMonthLabel }}</div>
                <div style="font-size: 22px; font-weight: 700; margin-top: 6px; color: var(--primary); font-variant-numeric:tabular-nums;">RM {{ number_format($thisMonthTotal, 2) }}</div>
            </div>
            <div class="hauz-card" style="padding: 16px;">
                <div style="font-size: 11.5px; color: var(--ink-3); text-transform:uppercase; letter-spacing:.4px;">{{ __('All-time total') }}</div>
                <div style="font-size: 22px; font-weight: 700; margin-top: 6px; color: var(--ink); font-variant-numeric:tabular-nums;">RM {{ number_format($grandTotal, 2) }}</div>
            </div>
            @foreach (array_slice($byCategory, 0, 2, true) as $cat => $sum)
                <div class="hauz-card" style="padding: 16px;">
                    <div style="font-size: 11.5px; color: var(--ink-3); text-transform:uppercase; letter-spacing:.4px;">{{ __($categories[$cat] ?? $cat) }}</div>
                    <div style="font-size: 22px; font-weight: 700; margin-top: 6px; color: {{ $catColor[$cat] ?? 'var(--ink)' }}; font-variant-numeric:tabular-nums;">RM {{ number_format($sum, 2) }}</div>
                </div>
            @endforeach
        </div>

        {{-- Add expense --}}
        <details class="hauz-card" style="padding: 0;" {{ $expenses->isEmpty() ? 'open' : '' }}>
            <summary style="padding: 14px 18px; cursor:pointer; font-weight:600; font-size:14px; list-style:none; display:flex; align-items:center; gap:8px;">
                <x-icon name="plus" :size="16" style="color: var(--primary);"/>
                {{ __('Add an expense') }}
            </summary>
            <form method="POST" action="{{ route('tenant.expenses.store') }}" style="padding: 4px 18px 18px;">
                @csrf
                <div class="ex-form-grid">
                    <div class="ex-field" style="grid-column: span 2;">
                        <label>{{ __('What was it for?') }}</label>
                        <input type="text" name="title" class="input" required maxlength="160"
                               placeholder="{{ __('e.g. Pool pump, 20L detergent, bathroom tiles') }}" value="{{ old('title') }}">
                    </div>
                    <div class="ex-field">
                        <label>{{ __('Category') }}</label>
                        <select name="category" class="input" required>
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}" @selected(old('category') === $key)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ex-field">
                        <label>{{ __('Amount (RM)') }}</label>
                        <input type="number" name="amount" class="input" required min="0" max="100000000" step="0.01"
                               placeholder="0.00" value="{{ old('amount') }}">
                    </div>
                    <div class="ex-field">
                        <label>{{ __('Date') }}</label>
                        <input type="date" name="incurred_at" class="input" required value="{{ old('incurred_at', $today) }}">
                    </div>
                    <div class="ex-field">
                        <label>{{ __('Homestay (optional)') }}</label>
                        <select name="property_id" class="input">
                            <option value="">{{ __('— All / general —') }}</option>
                            @foreach ($properties as $p)
                                <option value="{{ $p->id }}" @selected(old('property_id') == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ex-field">
                        <label>{{ __('Paid to (optional)') }}</label>
                        <input type="text" name="paid_to" class="input" maxlength="160"
                               placeholder="{{ __('Shop / contractor') }}" value="{{ old('paid_to') }}">
                    </div>
                    <div class="ex-field" style="grid-column: 1 / -1;">
                        <label>{{ __('Notes (optional)') }}</label>
                        <input type="text" name="description" class="input" maxlength="2000" value="{{ old('description') }}">
                    </div>
                </div>
                <div style="margin-top: 14px;">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save expense') }}</button>
                </div>
            </form>
        </details>

        {{-- Filters --}}
        @if ($months || $selectedCategory)
            <div style="display:flex; flex-direction:column; gap:10px;">
                <div class="ex-chips">
                    <a href="{{ route('tenant.expenses.index', array_filter(['category' => $selectedCategory])) }}"
                       class="ex-chip {{ ! $selectedMonth ? 'is-active' : '' }}">{{ __('All months') }}</a>
                    @foreach ($months as $m)
                        <a href="{{ route('tenant.expenses.index', array_filter(['month' => $m['key'], 'category' => $selectedCategory])) }}"
                           class="ex-chip {{ $selectedMonth === $m['key'] ? 'is-active' : '' }}">
                            {{ $m['label'] }} · RM {{ number_format($m['total'], 0) }}
                        </a>
                    @endforeach
                </div>
                @if (count($byCategory) > 1 || $selectedCategory)
                    <div class="ex-chips">
                        <a href="{{ route('tenant.expenses.index', array_filter(['month' => $selectedMonth])) }}"
                           class="ex-chip {{ ! $selectedCategory ? 'is-active' : '' }}">{{ __('All categories') }}</a>
                        @foreach ($byCategory as $cat => $sum)
                            <a href="{{ route('tenant.expenses.index', array_filter(['month' => $selectedMonth, 'category' => $cat])) }}"
                               class="ex-chip {{ $selectedCategory === $cat ? 'is-active' : '' }}">
                                <span style="width:7px; height:7px; border-radius:50%; background: {{ $catColor[$cat] ?? 'var(--ink-3)' }};"></span>
                                {{ __($categories[$cat] ?? $cat) }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Expense list --}}
        <div class="hauz-card ex-wrap" style="padding: 0; overflow: hidden;">
            <table class="ex-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Expense') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Homestay') }}</th>
                        <th class="ex-num">{{ __('Amount') }}</th>
                        <th class="ex-num"></th>
                    </tr>
                </thead>
                @forelse ($expenses as $e)
                    <tbody x-data="{ editing: false }">
                        {{-- Display row --}}
                        <tr x-show="!editing">
                            <td style="white-space:nowrap; width:1%;">{{ $e->incurred_at?->format('j M Y') }}</td>
                            <td>
                                <div style="font-weight:500; color:var(--ink);">{{ $e->title }}</div>
                                @if ($e->paid_to || $e->description)
                                    <div style="font-size:11px; color:var(--ink-3); margin-top:2px;">
                                        @if ($e->paid_to){{ $e->paid_to }}@endif
                                        @if ($e->paid_to && $e->description) · @endif
                                        @if ($e->description){{ $e->description }}@endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="ex-tag" style="background: color-mix(in srgb, {{ $catColor[$e->category] ?? 'var(--ink-3)' }} 14%, transparent); color: {{ $catColor[$e->category] ?? 'var(--ink-3)' }};">
                                    {{ __($categories[$e->category] ?? $e->category) }}
                                </span>
                            </td>
                            <td style="color:var(--ink-3);">{{ $e->property?->name ?? '—' }}</td>
                            <td class="ex-num ex-cost">RM {{ number_format((float) $e->amount, 2) }}</td>
                            <td class="ex-num" style="width:1%;">
                                <div class="ex-actions">
                                    <button type="button" class="ex-iconbtn" @click="editing = true" title="{{ __('Edit') }}"><x-icon name="pencil" :size="14"/></button>
                                    <form method="POST" action="{{ route('tenant.expenses.destroy', $e->id) }}" onsubmit="return confirm('{{ __('Delete this expense?') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ex-iconbtn" title="{{ __('Delete') }}" style="color:var(--err);"><x-icon name="trash" :size="14"/></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        {{-- Edit row --}}
                        <tr x-show="editing" x-cloak>
                            <td colspan="6" style="background: var(--bg-sunk);">
                                <form method="POST" action="{{ route('tenant.expenses.update', $e->id) }}">
                                    @csrf @method('PATCH')
                                    <div class="ex-form-grid">
                                        <div class="ex-field" style="grid-column: span 2;">
                                            <label>{{ __('What was it for?') }}</label>
                                            <input type="text" name="title" class="input" required maxlength="160" value="{{ $e->title }}">
                                        </div>
                                        <div class="ex-field">
                                            <label>{{ __('Category') }}</label>
                                            <select name="category" class="input" required>
                                                @foreach ($categories as $key => $label)
                                                    <option value="{{ $key }}" @selected($e->category === $key)>{{ __($label) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="ex-field">
                                            <label>{{ __('Amount (RM)') }}</label>
                                            <input type="number" name="amount" class="input" required min="0" max="100000000" step="0.01" value="{{ number_format((float) $e->amount, 2, '.', '') }}">
                                        </div>
                                        <div class="ex-field">
                                            <label>{{ __('Date') }}</label>
                                            <input type="date" name="incurred_at" class="input" required value="{{ $e->incurred_at?->format('Y-m-d') }}">
                                        </div>
                                        <div class="ex-field">
                                            <label>{{ __('Homestay (optional)') }}</label>
                                            <select name="property_id" class="input">
                                                <option value="">{{ __('— All / general —') }}</option>
                                                @foreach ($properties as $p)
                                                    <option value="{{ $p->id }}" @selected($e->property_id == $p->id)>{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="ex-field">
                                            <label>{{ __('Paid to (optional)') }}</label>
                                            <input type="text" name="paid_to" class="input" maxlength="160" value="{{ $e->paid_to }}">
                                        </div>
                                        <div class="ex-field" style="grid-column: 1 / -1;">
                                            <label>{{ __('Notes (optional)') }}</label>
                                            <input type="text" name="description" class="input" maxlength="2000" value="{{ $e->description }}">
                                        </div>
                                    </div>
                                    <div style="margin-top:12px; display:flex; gap:8px;">
                                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                                        <button type="button" class="btn btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="6" class="ex-empty">
                        {{ ($selectedMonth || $selectedCategory) ? __('No expenses match this filter.') : __('No expenses recorded yet. Add your first one above.') }}
                    </td></tr></tbody>
                @endforelse
                @if ($expenses->isNotEmpty())
                    <tfoot class="ex-tfoot"><tr>
                        <td colspan="4">{{ ($selectedMonth || $selectedCategory) ? __('Filtered total') : __('Total') }}</td>
                        <td class="ex-num ex-cost">RM {{ number_format($filteredTotal, 2) }}</td>
                        <td></td>
                    </tr></tfoot>
                @endif
            </table>
        </div>
    </div>
</x-app-layout>
