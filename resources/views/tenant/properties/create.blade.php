<x-app-layout :title="__('Add property')">
    <div style="max-width: 880px; margin: 0 auto; display:flex; flex-direction:column; gap: 20px;">
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

        <form method="POST" action="{{ route('tenant.properties.store') }}" style="display:flex; flex-direction:column; gap: 16px;">
            @csrf

            {{-- ─── Basics ─────────────────────────────────────────── --}}
            <div class="hauz-card" style="padding: 22px; display:flex; flex-direction:column; gap: 14px;">
                <div class="kicker">{{ __('Basics') }}</div>

                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Property name') }} *</label>
                    <input class="input" type="text" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="{{ __('e.g. Wafa Beach Villa') }}">
                </div>

                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Description') }}</label>
                    <textarea class="input" name="description" rows="4" maxlength="2000" style="height: auto; padding: 10px 12px; resize: vertical;" placeholder="{{ __('What makes this homestay special?') }}">{{ old('description') }}</textarea>
                </div>
            </div>

            {{-- ─── Location ───────────────────────────────────────── --}}
            <div class="hauz-card" style="padding: 22px; display:flex; flex-direction:column; gap: 14px;">
                <div class="kicker">{{ __('Location') }}</div>

                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Address line 1') }} *</label>
                    <input class="input" type="text" name="address_line1" value="{{ old('address_line1') }}" required maxlength="160" placeholder="{{ __('e.g. 12 Jalan Kelanang') }}">
                </div>

                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('City') }}</label>
                    <input class="input" type="text" name="city" value="{{ old('city') }}" maxlength="80" placeholder="{{ __('e.g. Port Dickson') }}">
                </div>
            </div>

            {{-- ─── Rooms & spaces ─────────────────────────────────── --}}
            <div class="hauz-card" style="padding: 22px; display:flex; flex-direction:column; gap: 14px;">
                <div class="kicker">{{ __('Rooms & spaces') }}</div>

                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <div>
                        <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Bedrooms') }} *</label>
                        <input class="input" type="number" name="bedrooms" value="{{ old('bedrooms', 1) }}" min="1" max="50" required>
                        <div style="font-size:11px; color: var(--ink-3); margin-top:4px;">{{ __('Bookable rooms') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Bathrooms') }}</label>
                        <input class="input" type="number" name="bathrooms" value="{{ old('bathrooms', 1) }}" min="0" max="50">
                        <div style="font-size:11px; color: var(--ink-3); margin-top:4px;">{{ __('Full bathrooms (shower + sink + toilet)') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Separate toilets') }}</label>
                        <input class="input" type="number" name="toilets" value="{{ old('toilets', 0) }}" min="0" max="50">
                        <div style="font-size:11px; color: var(--ink-3); margin-top:4px;">{{ __('Toilet-only / powder rooms') }}</div>
                    </div>
                </div>

                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Base price (RM/night)') }} *</label>
                    <input class="input" type="number" name="base_price" value="{{ old('base_price') }}" min="0" max="999999" step="0.01" required placeholder="220.00">
                    <div style="font-size: 11.5px; color: var(--ink-3); margin-top: 4px;">{{ __('Applied as the starting nightly rate to each room.') }}</div>
                </div>
            </div>

            {{-- ─── Facilities & amenities ─────────────────────────── --}}
            <div class="hauz-card" style="padding: 22px;">
                @include('tenant.properties._amenity_picker', [
                    'amenityGroups'      => $amenityGroups,
                    'selectedAmenityIds' => [],
                    'title'              => __('Facilities & amenities'),
                ])
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end; padding-top: 6px;">
                <a href="{{ route('tenant.properties.index') }}" class="btn">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Create property') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
