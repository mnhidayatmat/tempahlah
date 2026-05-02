<x-app-layout :title="__('Add property')">
    <div style="max-width: 640px; margin: 0 auto; display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.properties.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Back to properties') }}</a>
            <h2 class="display-2" style="margin: 8px 0 0;">{{ __('Add a property') }}</h2>
            <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">
                {{ __('Required fields are marked with *. You can edit everything later.') }}
            </p>
        </div>

        @if ($errors->any())
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <strong style="display:block; margin-bottom: 6px;">{{ __('Please fix the following:') }}</strong>
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tenant.properties.store') }}" class="hauz-card" style="padding: 22px; display:flex; flex-direction:column; gap: 16px;">
            @csrf

            <div>
                <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Property name') }} *</label>
                <input class="input" type="text" name="name" value="{{ old('name') }}" required maxlength="120">
            </div>

            <div>
                <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Address line 1') }} *</label>
                <input class="input" type="text" name="address_line1" value="{{ old('address_line1') }}" required maxlength="160" placeholder="{{ __('e.g. 12 Jalan Kelanang') }}">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('City') }}</label>
                    <input class="input" type="text" name="city" value="{{ old('city') }}" maxlength="80">
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Bedrooms') }} *</label>
                    <input class="input" type="number" name="bedrooms" value="{{ old('bedrooms', 1) }}" min="1" max="50" required>
                </div>
            </div>

            <div>
                <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Base price (RM/night)') }} *</label>
                <input class="input" type="number" name="base_price" value="{{ old('base_price') }}" min="0" max="999999" step="0.01" required>
                <div style="font-size: 11.5px; color: var(--ink-3); margin-top: 4px;">{{ __('Applied as the starting nightly rate to each room.') }}</div>
            </div>

            <div>
                <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Description') }}</label>
                <textarea class="input" name="description" rows="4" maxlength="2000" style="height: auto; padding: 10px 12px; resize: vertical;">{{ old('description') }}</textarea>
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end; padding-top: 6px;">
                <a href="{{ route('tenant.properties.index') }}" class="btn">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Create property') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
