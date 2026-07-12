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

        {{-- Inline validation banner — surfaces when the browser blocks submit on a required/invalid field --}}
        <div id="create-form-banner" role="alert"
             style="display:none; padding: 12px 16px; border-radius: var(--r-md); border:1px solid var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
        </div>

        <form id="create-property-form" method="POST" action="{{ route('tenant.properties.store') }}" style="display:flex; flex-direction:column; gap: 16px;">
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

                <x-location-picker />
                <div style="font-size: 11px; color: var(--ink-3);">
                    {{ app()->getLocale() === 'ms'
                        ? 'Pilih negeri & daerah supaya homestay anda muncul bila tetamu cari di marketplace.'
                        : 'Pick your state & district so your homestay shows up when guests search the marketplace.' }}
                </div>
            </div>

            {{-- ─── Rooms & pricing ────────────────────────────────── --}}
            <div class="hauz-card" style="padding: 22px; display:flex; flex-direction:column; gap: 14px;"
                 x-data="{ mode: '{{ old('pricing_mode', 'whole_house') }}', bedrooms: {{ (int) old('bedrooms', 1) }} }">
                <div class="kicker">{{ __('Rooms & pricing') }}</div>

                {{-- Bedrooms / bathrooms / toilets are always shown (descriptive counts) --}}
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <div>
                        <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Bedrooms') }} *</label>
                        <input class="input" type="number" name="bedrooms" x-model.number="bedrooms" min="1" max="50" required>
                        <div style="font-size:11px; color: var(--ink-3); margin-top:4px;">{{ __('Bilik tidur') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Bathrooms') }}</label>
                        <input class="input" type="number" name="bathrooms" value="{{ old('bathrooms', 1) }}" min="0" max="50">
                        <div style="font-size:11px; color: var(--ink-3); margin-top:4px;">{{ __('Bilik air lengkap') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Separate toilets') }}</label>
                        <input class="input" type="number" name="toilets" value="{{ old('toilets', 0) }}" min="0" max="50">
                        <div style="font-size:11px; color: var(--ink-3); margin-top:4px;">{{ __('Tandas berasingan') }}</div>
                    </div>
                </div>

                {{-- Pricing mode toggle — TWO-OPTION CARD GROUP --}}
                <div>
                    <label class="kicker" style="display:block; margin-bottom:8px;">{{ __('How do you charge?') }} *</label>
                    <input type="hidden" name="pricing_mode" x-bind:value="mode">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        {{-- Whole-house (default) --}}
                        <button type="button"
                                @click="mode = 'whole_house'"
                                x-bind:class="mode === 'whole_house' ? 'is-active' : ''"
                                style="cursor:pointer; text-align:left; padding: 14px; border-radius: var(--r-md);
                                       border: 2px solid var(--line); background: var(--bg-elev);
                                       transition: all 120ms; display:flex; flex-direction:column; gap:6px;"
                                x-bind:style="mode === 'whole_house'
                                    ? 'border-color: var(--primary); background: var(--primary-tint); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent);'
                                    : ''">
                            <div style="display:flex; align-items:center; gap: 6px;">
                                <span style="font-size: 18px;">🏠</span>
                                <span style="font-weight: 700; font-size: 13.5px;">{{ __('Whole house') }}</span>
                                <span style="font-size: 10px; padding: 2px 6px; border-radius: 6px; background: var(--bg-tint); color: var(--ink-3); margin-left: auto;">{{ __('Default') }}</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--ink-3); line-height: 1.4;">
                                {{ __('One flat price per night for the entire property. Guests book the whole house — bedroom count is just for them to know.') }}
                            </div>
                        </button>

                        {{-- Per-room --}}
                        <button type="button"
                                @click="mode = 'per_room'"
                                x-bind:class="mode === 'per_room' ? 'is-active' : ''"
                                style="cursor:pointer; text-align:left; padding: 14px; border-radius: var(--r-md);
                                       border: 2px solid var(--line); background: var(--bg-elev);
                                       transition: all 120ms; display:flex; flex-direction:column; gap:6px;"
                                x-bind:style="mode === 'per_room'
                                    ? 'border-color: var(--primary); background: var(--primary-tint); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent);'
                                    : ''">
                            <div style="display:flex; align-items:center; gap: 6px;">
                                <span style="font-size: 18px;">🛏️</span>
                                <span style="font-weight: 700; font-size: 13.5px;">{{ __('Per room') }}</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--ink-3); line-height: 1.4;">
                                {{ __('Each room books and prices independently. For boutique stays, hostels, or shared houses where multiple groups can book different rooms.') }}
                            </div>
                        </button>
                    </div>
                </div>

                {{-- Whole-house: one price + max guests --}}
                {{-- NOTE: x-bind:disabled prevents the hidden input from being submitted.
                     Without this, both inputs share name="base_price" and the hidden one
                     (always empty) would overwrite the visible one's value on POST. --}}
                <div x-show="mode === 'whole_house'" x-transition style="display:grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                    <div>
                        <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Price for the whole house (RM/night)') }} *</label>
                        <input class="input" type="number" name="base_price" value="{{ old('base_price') }}" min="0" max="999999" step="0.01"
                               x-bind:required="mode === 'whole_house'"
                               x-bind:disabled="mode !== 'whole_house'"
                               placeholder="220">
                        <div style="font-size: 11.5px; color: var(--ink-3); margin-top: 4px;">
                            {{ __('Flat nightly rate, regardless of bedroom count. e.g. RM 220 / night for 3 guests or 8 guests.') }}
                        </div>
                    </div>
                    <div>
                        <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Max guests') }}</label>
                        <input class="input" type="number" name="max_guests" value="{{ old('max_guests') }}" min="1" max="200"
                               x-bind:disabled="mode !== 'whole_house'"
                               x-bind:placeholder="bedrooms * 2">
                        <div style="font-size: 11.5px; color: var(--ink-3); margin-top: 4px;">
                            {{ __('Default: 2 × bedrooms') }}
                        </div>
                    </div>
                </div>

                {{-- Per-room: one starting price (applied to all N rooms) --}}
                <div x-show="mode === 'per_room'" x-transition>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Starting price per room (RM/night)') }} *</label>
                    <input class="input" type="number" name="base_price" value="{{ old('base_price') }}" min="0" max="999999" step="0.01"
                           x-bind:required="mode === 'per_room'"
                           x-bind:disabled="mode !== 'per_room'"
                           placeholder="120">
                    <div style="font-size: 11.5px; color: var(--ink-3); margin-top: 4px;">
                        <span x-text="`${bedrooms} bookable room(s) will be created at this rate. You can adjust each room's price later on the edit page.`"></span>
                    </div>
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

    {{-- Surface silent HTML5 validation failures: scroll to + highlight the first invalid field --}}
    <script>
        (function () {
            const form   = document.getElementById('create-property-form');
            const banner = document.getElementById('create-form-banner');
            if (!form || !banner) return;

            const labelFor = (el) => {
                const lblEl = el.closest('div')?.querySelector('label');
                return (lblEl?.textContent || el.name || '').trim().replace(/\s*\*\s*$/, '');
            };

            form.addEventListener('invalid', function (e) {
                const field = e.target;
                // Stop the browser's native tooltip on subsequent invalid fields, we show one banner.
                e.preventDefault();
                if (!form.dataset._hadInvalid) {
                    form.dataset._hadInvalid = '1';
                    const msg = field.validationMessage || @json(__('Please fill out this field.'));
                    banner.textContent = `${labelFor(field)}: ${msg}`;
                    banner.style.display = 'block';
                    banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    field.focus({ preventScroll: true });
                    field.style.borderColor = 'var(--err)';
                    field.addEventListener('input', () => {
                        field.style.borderColor = '';
                    }, { once: true });
                }
            }, true);

            form.addEventListener('submit', function () {
                // Reset the "had invalid" flag so subsequent invalid attempts re-trigger the banner.
                delete form.dataset._hadInvalid;
                banner.style.display = 'none';
            });
        })();
    </script>
</x-app-layout>
