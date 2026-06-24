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
                <input type="text" name="phone" class="input" maxlength="40" value="{{ old('phone') }}" placeholder="012-3456789">
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
    <div class="hauz-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 14px 18px; border-bottom: .5px solid var(--line); font-weight: 600; font-size: 14px;">
            {{ __('Total') }}: {{ $people->count() }}
        </div>
        @forelse ($people as $p)
            <div x-data="{ editing: false }" style="border-top: .5px solid var(--line);">
                {{-- Display row --}}
                <div x-show="!editing" style="padding: 14px 18px; display:grid; grid-template-columns: auto 1fr auto; gap: 16px; align-items:center;">
                    <x-avatar :name="$p->name" :size="36"/>
                    <div style="min-width: 0;">
                        <div style="display:flex; align-items:center; gap: 8px; flex-wrap: wrap;">
                            <span style="font-weight: 600; font-size: 14px;">{{ $p->name }}</span>
                            @unless ($p->is_active)
                                <span class="pill" style="background: var(--bg-sunk); color: var(--ink-3); height: 18px; font-size: 10.5px;">{{ __('Inactive') }}</span>
                            @endunless
                        </div>
                        <div style="font-size: 12.5px; color: var(--ink-2); display:flex; gap: 14px; flex-wrap: wrap; margin-top: 2px;">
                            @if ($p->phone)<span>📞 {{ $p->phone }}</span>@endif
                            @if ($p->email)<span>✉️ {{ $p->email }}</span>@endif
                            @if ($countAttr)<span style="color: var(--ink-3);">{{ $p->{$countAttr} }} {{ $countNoun }}</span>@endif
                        </div>
                        @if ($p->bank_account_no)
                            <div style="font-size: 12px; color: var(--ink-2); margin-top: 4px;">
                                🏦 {{ $p->bank_name ? $p->bank_name.' · ' : '' }}<span class="mono">{{ $p->bank_account_no }}</span>{{ $p->bank_account_holder ? ' · '.$p->bank_account_holder : '' }}
                            </div>
                        @endif
                    </div>
                    <button type="button" class="btn btn-sm" @click="editing = true">{{ __('Edit') }}</button>
                </div>

                {{-- Edit form --}}
                <div x-show="editing" x-cloak style="padding: 14px 18px; display:flex; flex-direction:column; gap: 12px;">
                    <form method="POST" action="{{ route($updateRoute, $p->id) }}"
                          style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px; align-items:end;">
                        @csrf @method('PATCH')
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Name') }} *</label>
                            <input type="text" name="name" class="input" maxlength="120" required value="{{ $p->name }}">
                        </div>
                        <div>
                            <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Phone') }}</label>
                            <input type="text" name="phone" class="input" maxlength="40" value="{{ $p->phone }}">
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
                    <form method="POST" action="{{ route($destroyRoute, $p->id) }}" onsubmit="return confirm('{{ $removeConfirm ?? __('Remove this entry?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm" style="color: var(--err);">{{ $removeLabel ?? __('Remove') }}</button>
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
