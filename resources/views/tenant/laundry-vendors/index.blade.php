<x-app-layout :title="__('Laundry vendors')">
    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Operations') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Laundry vendors') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ __('Register your laundry vendors (dobi), then assign them to laundry batches.') }}
                </div>
            </div>
            <a href="{{ route('tenant.housekeeping.index', ['tab' => 'laundry']) }}" class="btn btn-sm">{{ __('Back to Housekeeping') }}</a>
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

        {{-- Register a new vendor --}}
        <div class="hauz-card" style="padding: 18px;">
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 12px;">{{ __('Register a vendor') }}</div>
            <form method="POST" action="{{ route('tenant.laundry-vendors.store') }}"
                  style="display:grid; grid-template-columns: 2fr 1.4fr 1.8fr auto; gap: 12px; align-items:end;">
                @csrf
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Name') }} *</label>
                    <input type="text" name="name" class="input" maxlength="120" required value="{{ old('name') }}" placeholder="{{ __('e.g. Dobi Mesra') }}">
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
                    <button type="submit" class="btn btn-primary">{{ __('Add vendor') }}</button>
                </div>
            </form>
        </div>

        {{-- Vendor list --}}
        <div class="hauz-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 18px; border-bottom: .5px solid var(--line); font-weight: 600; font-size: 14px;">
                {{ __('Your vendors') }} ({{ $vendors->count() }})
            </div>
            @forelse ($vendors as $v)
                <div x-data="{ editing: false }" style="border-top: .5px solid var(--line);">
                    {{-- Display row --}}
                    <div x-show="!editing" style="padding: 14px 18px; display:grid; grid-template-columns: auto 1fr auto; gap: 16px; align-items:center;">
                        <x-avatar :name="$v->name" :size="36"/>
                        <div style="min-width: 0;">
                            <div style="display:flex; align-items:center; gap: 8px; flex-wrap: wrap;">
                                <span style="font-weight: 600; font-size: 14px;">{{ $v->name }}</span>
                                @unless ($v->is_active)
                                    <span class="pill" style="background: var(--bg-sunk); color: var(--ink-3); height: 18px; font-size: 10.5px;">{{ __('Inactive') }}</span>
                                @endunless
                            </div>
                            <div style="font-size: 12.5px; color: var(--ink-2); display:flex; gap: 14px; flex-wrap: wrap; margin-top: 2px;">
                                @if ($v->phone)<span>📞 {{ $v->phone }}</span>@endif
                                @if ($v->email)<span>✉️ {{ $v->email }}</span>@endif
                                <span style="color: var(--ink-3);">{{ $v->laundry_tasks_count }} {{ __('batch(es)') }}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm" @click="editing = true">{{ __('Edit') }}</button>
                    </div>

                    {{-- Edit form --}}
                    <div x-show="editing" x-cloak style="padding: 14px 18px; display:flex; flex-direction:column; gap: 12px;">
                        <form method="POST" action="{{ route('tenant.laundry-vendors.update', $v->id) }}"
                              style="display:grid; grid-template-columns: 2fr 1.4fr 1.8fr auto; gap: 12px; align-items:end;">
                            @csrf @method('PATCH')
                            <div>
                                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Name') }} *</label>
                                <input type="text" name="name" class="input" maxlength="120" required value="{{ $v->name }}">
                            </div>
                            <div>
                                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Phone') }}</label>
                                <input type="text" name="phone" class="input" maxlength="40" value="{{ $v->phone }}">
                            </div>
                            <div>
                                <label class="kicker" style="display:block; margin-bottom: 4px;">{{ __('Email') }}</label>
                                <input type="email" name="email" class="input" maxlength="160" value="{{ $v->email }}">
                            </div>
                            <div style="display:flex; align-items:center; gap: 10px;">
                                <label style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; white-space: nowrap;">
                                    <input type="checkbox" name="is_active" value="1" @checked($v->is_active)> {{ __('Active') }}
                                </label>
                            </div>
                            <div style="grid-column: span 4; display:flex; justify-content:flex-end; gap: 8px;">
                                <button type="button" class="btn btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('tenant.laundry-vendors.destroy', $v->id) }}" onsubmit="return confirm('{{ __('Remove this vendor?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm" style="color: var(--err);">{{ __('Remove vendor') }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="padding: 32px; text-align: center; color: var(--ink-3); font-size: 13px;">
                    {{ __('No vendors yet — add one above.') }}
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
