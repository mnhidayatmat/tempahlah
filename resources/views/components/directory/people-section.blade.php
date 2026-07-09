@props([
    'people',
    'storeRoute',
    'updateRoute',
    'destroyRoute',
    'addLabel' => null,
    'namePlaceholder' => null,
    'emptyText' => null,
    'countAttr' => null,
    'countNoun' => '',
    'removeConfirm' => null,
    'removeLabel' => null,
])
@once
    <style>
        .dir-total-pill {
            background: var(--bg-sunk); color: var(--ink-2); font-weight: 600;
            font-size: 11px; padding: 2px 9px; border-radius: 999px;
        }
        .dir-item + .dir-item { border-top: .5px solid var(--line); }
        .dir-row {
            padding: 15px 18px; display:grid; grid-template-columns: 1fr auto;
            gap: 16px; align-items:center; transition: background .12s;
        }
        .dir-row:hover { background: var(--bg-sunk); }
        .dir-count {
            font-size: 11px; color: var(--ink-3); background: var(--bg-sunk);
            padding: 1px 8px; border-radius: 999px; white-space: nowrap;
        }
        .dir-meta {
            font-size: 12.5px; color: var(--ink-2); display:flex; gap: 16px;
            flex-wrap: wrap; margin-top: 4px;
        }
        .dir-meta span { display:inline-flex; align-items:center; gap: 5px; }
        .dir-meta svg, .dir-bank svg { color: var(--ink-3); flex-shrink: 0; }
        .dir-bank {
            font-size: 12px; color: var(--ink-2); margin-top: 5px;
            display:inline-flex; align-items:center; gap: 5px;
        }
        .dir-actions { display:flex; align-items:center; gap: 4px; }
        .dir-iconbtn {
            width: 34px; height: 34px; border-radius: 9px; border: none;
            background: transparent; color: var(--ink-3); cursor: pointer;
            display:inline-flex; align-items:center; justify-content:center;
            transition: background .12s, color .12s;
        }
        .dir-iconbtn:hover { background: var(--primary-tint); color: var(--primary); }
        .dir-iconbtn-danger:hover { background: var(--err-tint); color: var(--err); }
        [x-cloak] { display: none !important; }
    </style>
@endonce
<div style="display:flex; flex-direction:column; gap: 16px;">
    {{-- Register form --}}
    <div class="hauz-card" style="padding: 18px;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 12px;">{{ $addLabel ?? __('Add') }}</div>
        <form method="POST" action="{{ route($storeRoute) }}"
              style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px; align-items:end;">
            @csrf
            <div>
                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Name') }} *</label>
                <input type="text" name="name" class="input" maxlength="120" required value="{{ old('name') }}" placeholder="{{ $namePlaceholder }}">
            </div>
            <div>
                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Phone') }}</label>
                <input type="tel" inputmode="tel" name="phone" class="input" maxlength="40" value="{{ old('phone') }}" placeholder="+60123456789" data-phone-input>
            </div>
            <div>
                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Email') }} <span style="color:var(--ink-3); text-transform:none; letter-spacing:0;">({{ __('optional') }})</span></label>
                <input type="email" name="email" class="input" maxlength="160" value="{{ old('email') }}" placeholder="name@example.com">
            </div>
            <div>
                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Bank') }}</label>
                <input type="text" name="bank_name" class="input" maxlength="120" value="{{ old('bank_name') }}" placeholder="{{ __('e.g. Maybank') }}">
            </div>
            <div>
                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Account no.') }}</label>
                <input type="text" name="bank_account_no" class="input" maxlength="60" value="{{ old('bank_account_no') }}" placeholder="1234 5678 9012" autocomplete="off">
            </div>
            <div>
                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Account holder') }}</label>
                <input type="text" name="bank_account_holder" class="input" maxlength="120" value="{{ old('bank_account_holder') }}" placeholder="{{ __('if different from name') }}">
            </div>
            <div style="grid-column: 1 / -1; text-align: right;">
                <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
            </div>
        </form>
    </div>

    {{-- List --}}
    <div class="hauz-card dir-list" style="padding: 0; overflow: hidden;">
        <div style="padding: 13px 18px; border-bottom: .5px solid var(--line); font-weight: 600; font-size: 13px; color: var(--ink-2); display:flex; align-items:center; justify-content:space-between;">
            <span>{{ __('Total') }}</span>
            <span class="dir-total-pill">{{ $people->count() }}</span>
        </div>
        @forelse ($people as $p)
            <div x-data="{ editing: false }" class="dir-item">
                {{-- Display row --}}
                <div x-show="!editing" class="dir-row">
                    <div style="min-width: 0;">
                        <div style="display:flex; align-items:center; gap: 8px; flex-wrap: wrap;">
                            <span style="font-weight: 600; font-size: 14.5px; color: var(--ink);">{{ $p->name }}</span>
                            @unless ($p->is_active)
                                <span class="pill" style="background: var(--bg-sunk); color: var(--ink-3); height: 18px; font-size: 10.5px;">{{ __('Inactive') }}</span>
                            @endunless
                            @if ($countAttr)
                                <span class="dir-count">{{ $p->{$countAttr} }} {{ $countNoun }}</span>
                            @endif
                        </div>
                        <div class="dir-meta">
                            @if ($p->phone)<span><x-icon name="phone" :size="13"/> {{ $p->phone }}</span>@endif
                            @if ($p->email)<span><x-icon name="mail" :size="13"/> {{ $p->email }}</span>@endif
                            @if (! $p->phone && ! $p->email)<span style="color: var(--ink-3);">{{ __('No contact details') }}</span>@endif
                        </div>
                        @if ($p->bank_account_no)
                            <div class="dir-bank">
                                <x-icon name="card" :size="13"/>
                                <span>{{ $p->bank_name ? $p->bank_name.' · ' : '' }}<span class="mono">{{ $p->bank_account_no }}</span>{{ $p->bank_account_holder ? ' · '.$p->bank_account_holder : '' }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="dir-actions">
                        <button type="button" class="dir-iconbtn" @click="editing = true" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}">
                            <x-icon name="pencil" :size="16"/>
                        </button>
                        <form method="POST" action="{{ route($destroyRoute, $p->id) }}" onsubmit="return confirm('{{ $removeConfirm ?? __('Remove this entry?') }}')" style="display:inline-flex;">
                            @csrf @method('DELETE')
                            <button type="submit" class="dir-iconbtn dir-iconbtn-danger" title="{{ $removeLabel ?? __('Remove') }}">
                                <x-icon name="trash" :size="16"/>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Edit form --}}
                <div x-show="editing" x-cloak style="padding: 16px 18px; background: var(--bg-sunk); display:flex; flex-direction:column; gap: 12px;">
                    <form method="POST" action="{{ route($updateRoute, $p->id) }}"
                          style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px; align-items:end;">
                        @csrf @method('PATCH')
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Name') }} *</label>
                            <input type="text" name="name" class="input" maxlength="120" required value="{{ $p->name }}">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Phone') }}</label>
                            <input type="tel" inputmode="tel" name="phone" class="input" maxlength="40" value="{{ $p->phone }}" data-phone-input>
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Email') }}</label>
                            <input type="email" name="email" class="input" maxlength="160" value="{{ $p->email }}">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Bank') }}</label>
                            <input type="text" name="bank_name" class="input" maxlength="120" value="{{ $p->bank_name }}" placeholder="{{ __('e.g. Maybank') }}">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Account no.') }}</label>
                            <input type="text" name="bank_account_no" class="input" maxlength="60" value="{{ $p->bank_account_no }}" autocomplete="off">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Account holder') }}</label>
                            <input type="text" name="bank_account_holder" class="input" maxlength="120" value="{{ $p->bank_account_holder }}">
                        </div>
                        <div style="display:flex; align-items:center; gap: 10px;">
                            <label style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; white-space: nowrap;">
                                <input type="checkbox" name="is_active" value="1" @checked($p->is_active)> {{ __('Active') }}
                            </label>
                        </div>
                        <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end; gap: 8px;">
                            <button type="button" class="btn btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div style="padding: 32px; text-align: center; color: var(--ink-3); font-size: 13px;">
                {{ $emptyText ?? __('Nothing here yet — add one above.') }}
            </div>
        @endforelse
    </div>
</div>
