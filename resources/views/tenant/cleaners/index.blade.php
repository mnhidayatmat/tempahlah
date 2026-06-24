<x-app-layout :title="__('Cleaners')">
    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Operations') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Cleaners') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ __('Register your cleaners, then assign them to cleaning tasks.') }}
                </div>
            </div>
            <a href="{{ route('tenant.housekeeping.index') }}" class="btn btn-sm">{{ __('Back to Housekeeping') }}</a>
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

        {{-- Register a new cleaner --}}
        <div class="hauz-card" style="padding: 18px;">
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 12px;">{{ __('Register a cleaner') }}</div>
            <form method="POST" action="{{ route('tenant.cleaners.store') }}"
                  style="display:grid; grid-template-columns: 2fr 1.4fr 1.8fr auto; gap: 12px; align-items:end;">
                @csrf
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Name') }} *</label>
                    <input type="text" name="name" class="input" maxlength="120" required value="{{ old('name') }}" placeholder="{{ __('e.g. Kak Minah') }}">
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
                    <button type="submit" class="btn btn-primary">{{ __('Add cleaner') }}</button>
                </div>
            </form>
        </div>

        {{-- Cleaner list --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 18px; border-bottom: .5px solid var(--line); font-weight: 600; font-size: 14px;">
                {{ __('Your cleaners') }} ({{ $cleaners->count() }})
            </div>
            @forelse ($cleaners as $c)
                <div x-data="{ editing: false }" style="border-top: .5px solid var(--line);">
                    {{-- Display row --}}
                    <div x-show="!editing" style="padding: 14px 18px; display:grid; grid-template-columns: auto 1fr auto; gap: 16px; align-items:center;">
                        <x-avatar :name="$c->name" :size="36"/>
                        <div style="min-width: 0;">
                            <div style="display:flex; align-items:center; gap: 8px; flex-wrap: wrap;">
                                <span style="font-weight: 600; font-size: 14px;">{{ $c->name }}</span>
                                @unless ($c->is_active)
                                    <span class="pill" style="background: var(--bg-sunk); color: var(--ink-3); height: 18px; font-size: 10.5px;">{{ __('Inactive') }}</span>
                                @endunless
                            </div>
                            <div style="font-size: 12.5px; color: var(--ink-2); display:flex; gap: 14px; flex-wrap: wrap; margin-top: 2px;">
                                @if ($c->phone)<span>📞 {{ $c->phone }}</span>@endif
                                @if ($c->email)<span>✉️ {{ $c->email }}</span>@endif
                                <span style="color: var(--ink-3);">{{ $c->cleaning_tasks_count }} {{ __('task(s)') }}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm" @click="editing = true">{{ __('Edit') }}</button>
                    </div>

                    {{-- Edit form --}}
                    <div x-show="editing" x-cloak style="padding: 14px 18px; display:flex; flex-direction:column; gap: 12px;">
                        <form method="POST" action="{{ route('tenant.cleaners.update', $c->id) }}"
                              style="display:grid; grid-template-columns: 2fr 1.4fr 1.8fr auto; gap: 12px; align-items:end;">
                            @csrf @method('PATCH')
                            <div>
                                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Name') }} *</label>
                                <input type="text" name="name" class="input" maxlength="120" required value="{{ $c->name }}">
                            </div>
                            <div>
                                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Phone') }}</label>
                                <input type="text" name="phone" class="input" maxlength="40" value="{{ $c->phone }}">
                            </div>
                            <div>
                                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Email') }}</label>
                                <input type="email" name="email" class="input" maxlength="160" value="{{ $c->email }}">
                            </div>
                            <div style="display:flex; align-items:center; gap: 10px;">
                                <label style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; white-space: nowrap;">
                                    <input type="checkbox" name="is_active" value="1" @checked($c->is_active)> {{ __('Active') }}
                                </label>
                            </div>
                            <div style="grid-column: span 4; display:flex; justify-content:flex-end; gap: 8px;">
                                <button type="button" class="btn btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('tenant.cleaners.destroy', $c->id) }}" onsubmit="return confirm('{{ __('Remove this cleaner?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm" style="color: var(--err);">{{ __('Remove cleaner') }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="padding: 32px; text-align: center; color: var(--ink-3); font-size: 13px;">
                    {{ __('No cleaners yet — add one above.') }}
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
