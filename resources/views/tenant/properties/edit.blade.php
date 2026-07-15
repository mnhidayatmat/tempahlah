<x-app-layout :title="__('Edit :name', ['name' => $property->name])" :breadcrumbs="[__('Settings'), __('Edit')]">
    <style>
        .eh-wrap { max-width: 780px; margin: 0 auto; display:flex; flex-direction:column; gap: 18px; padding-bottom: 8px; }

        /* Header */
        .eh-back { font-size:12.5px; color:var(--ink-3); text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
        .eh-back:hover { color:var(--ink-2); }
        .eh-titlerow { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-top:10px; }
        .eh-sub { margin:6px 0 0; color:var(--ink-3); font-size:13px; }

        /* Section card */
        .eh-card { padding:0 !important; overflow:hidden; }
        .eh-chead { display:flex; align-items:flex-start; gap:12px; padding:16px 20px; border-bottom:.5px solid var(--line); }
        .eh-ico { width:36px; height:36px; border-radius:10px; background:var(--primary-tint); color:var(--primary-deep);
                  display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
        .eh-ctitle { font-size:15px; font-weight:600; color:var(--ink); letter-spacing:-.01em; }
        .eh-csub { font-size:12.5px; color:var(--ink-3); margin-top:2px; line-height:1.45; }
        .eh-body { padding:20px; display:flex; flex-direction:column; gap:16px; }

        /* Fields */
        .eh-field { display:flex; flex-direction:column; min-width:0; }
        .eh-label { font-size:12.5px; font-weight:600; color:var(--ink-2); margin-bottom:6px; display:flex; align-items:baseline; gap:6px; }
        .eh-req { color:var(--primary); font-weight:700; }
        .eh-opt { font-weight:500; font-size:11px; color:var(--ink-4); }
        .eh-hint { font-size:11.5px; color:var(--ink-3); margin-top:6px; line-height:1.5; }
        .eh-err  { font-size:11.5px; color:var(--err); margin-top:6px; }

        .eh-grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .eh-grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
        @media (max-width:640px){ .eh-grid-2,.eh-grid-3{ grid-template-columns:1fr; } }

        /* Sticky save bar */
        .eh-actions { position:sticky; bottom:12px; z-index:5; margin-top:4px;
                      display:flex; justify-content:space-between; align-items:center; gap:12px;
                      padding:12px 16px; background:var(--bg-elev); border:.5px solid var(--line);
                      border-radius:var(--r-lg); box-shadow:0 6px 24px -8px rgba(20,20,30,.18), 0 2px 6px rgba(20,20,30,.06); }
        .eh-actions-right { display:flex; gap:8px; }
        @media (max-width:640px){
            .eh-actions{ flex-direction:column-reverse; align-items:stretch; bottom:0; border-radius:var(--r-md); }
            .eh-actions-right{ flex-direction:column; }
            .eh-actions .btn{ justify-content:center; width:100%; }
        }
    </style>

    @php
        $isWholeHouse = $property->isWholeHousePricing();
        $singleRoom = $isWholeHouse ? $property->rooms->first() : null;
        $statusMeta = [
            'active'   => ['ok',      __('Active')],
            'draft'    => ['warn',    __('Draft')],
            'archived' => ['default', __('Archived')],
        ][$property->status] ?? ['default', ucfirst($property->status)];
    @endphp

    <div class="eh-wrap">

        {{-- Header --}}
        <div>
            <a href="{{ route('tenant.settings.index') }}" class="eh-back">← {{ __('Back to settings') }}</a>
            <div class="eh-titlerow">
                <h2 class="display-2" style="margin:0;">{{ __('Edit homestay') }}</h2>
                <x-pill :variant="$statusMeta[0]" :dot="true">{{ $statusMeta[1] }}</x-pill>
            </div>
            <p class="eh-sub">
                {{ $property->name }} · <span style="font-family:var(--font-mono);">{{ $property->public_id }}</span>
            </p>
        </div>

        @if ($errors->any())
            <div class="hauz-card" style="padding:14px 16px; border-color:var(--err); background:var(--err-tint); color:var(--err); font-size:13px;">
                <strong style="display:block; margin-bottom:6px;">{{ __('Please fix the following:') }}</strong>
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if (session('status'))
            <div class="hauz-card" style="padding:12px 16px; background:var(--ok-tint); color:var(--ok); border-color:var(--ok); font-size:13px;">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('tenant.properties.update', ['property' => $property->public_id]) }}" style="display:flex; flex-direction:column; gap:18px;">
            @csrf
            @method('PATCH')

            {{-- ── Basic info ─────────────────────────────────────────── --}}
            <div class="card eh-card">
                <div class="eh-chead">
                    <span class="eh-ico"><x-icon name="building" :size="18"/></span>
                    <div>
                        <div class="eh-ctitle">{{ __('Basic info') }}</div>
                        <div class="eh-csub">{{ __('Name, visibility and the description guests read.') }}</div>
                    </div>
                </div>
                <div class="eh-body">
                    <div class="eh-field">
                        <label class="eh-label">{{ __('Homestay name') }} <span class="eh-req">*</span></label>
                        <input class="input" type="text" name="name" value="{{ old('name', $property->name) }}" required maxlength="120">
                    </div>
                    <div class="eh-field">
                        <label class="eh-label">{{ __('Status') }} <span class="eh-req">*</span></label>
                        <select class="input" name="status">
                            <option value="draft"    {{ old('status', $property->status) === 'draft'    ? 'selected' : '' }}>{{ __('Draft — hidden from public booking page') }}</option>
                            <option value="active"   {{ old('status', $property->status) === 'active'   ? 'selected' : '' }}>{{ __('Active — visible on public booking page') }}</option>
                            <option value="archived" {{ old('status', $property->status) === 'archived' ? 'selected' : '' }}>{{ __('Archived — no new bookings') }}</option>
                        </select>
                    </div>
                    <div class="eh-grid-2">
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Description (English)') }}</label>
                            <textarea class="input" name="description_en" rows="4" maxlength="2000" style="height:auto; padding:10px 12px; font-family:inherit; resize:vertical;">{{ old('description_en', $property->description_en) }}</textarea>
                        </div>
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Description (BM)') }}</label>
                            <textarea class="input" name="description_bm" rows="4" maxlength="2000" style="height:auto; padding:10px 12px; font-family:inherit; resize:vertical;">{{ old('description_bm', $property->description_bm) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Location ───────────────────────────────────────────── --}}
            <div class="card eh-card">
                <div class="eh-chead">
                    <span class="eh-ico"><x-icon name="pin" :size="18"/></span>
                    <div>
                        <div class="eh-ctitle">{{ __('Location') }}</div>
                        <div class="eh-csub">
                            {{ app()->getLocale() === 'ms'
                                ? 'Pilih negeri & daerah supaya homestay anda muncul dalam carian marketplace.'
                                : 'Pick your state & district so your homestay shows up in marketplace search.' }}
                        </div>
                    </div>
                </div>
                <div class="eh-body">
                    <div class="eh-grid-2">
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Address line 1') }} <span class="eh-req">*</span></label>
                            <input class="input" type="text" name="address_line1" value="{{ old('address_line1', $property->address_line1) }}" required maxlength="160">
                        </div>
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Address line 2') }} <span class="eh-opt">{{ __('optional') }}</span></label>
                            <input class="input" type="text" name="address_line2" value="{{ old('address_line2', $property->address_line2) }}" maxlength="160">
                        </div>
                    </div>

                    <x-location-picker :state="$property->state" :district="$property->city" :postcode="$property->postcode" />

                    <div class="eh-field">
                        <label class="eh-label">
                            {{ __('Google Maps URL') }}
                            <span class="eh-opt">{{ __('optional — for a precise pin') }}</span>
                        </label>
                        <input class="input" type="url" name="map_url"
                               value="{{ old('map_url', $property->map_url) }}"
                               maxlength="500" placeholder="https://maps.app.goo.gl/...">
                        <p class="eh-hint">
                            {{ app()->getLocale() === 'ms'
                                ? 'Buka Google Maps, cari rumah anda, tekan Share → Copy link, tampal di sini. Tetamu yang tekan “Arah” akan terus ke pin tepat.'
                                : 'Open Google Maps, find your property, tap Share → Copy link, paste it here. Guests who tap “Direction” jump straight to the exact pin.' }}
                        </p>
                        @error('map_url')<p class="eh-err">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- ── Rooms & capacity ───────────────────────────────────── --}}
            <div class="card eh-card">
                <div class="eh-chead">
                    <span class="eh-ico"><x-icon name="bed" :size="18"/></span>
                    <div>
                        <div class="eh-ctitle">{{ __('Rooms & capacity') }}</div>
                        <div class="eh-csub">{{ __('How many rooms the place has and how many guests it sleeps.') }}</div>
                    </div>
                </div>
                <div class="eh-body">
                    <div class="eh-grid-3">
                        @if ($isWholeHouse)
                            <div class="eh-field">
                                <label class="eh-label">{{ __('Bedrooms') }}</label>
                                <input class="input" type="number" name="bedrooms" value="{{ old('bedrooms', $property->bedroomCount() ?: 1) }}" min="1" max="50">
                                <div class="eh-hint">{{ __('Bedrooms in the whole house') }}</div>
                            </div>
                        @endif
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Bathrooms') }}</label>
                            <input class="input" type="number" name="bathrooms" value="{{ old('bathrooms', $property->bathrooms) }}" min="0" max="50">
                            <div class="eh-hint">{{ __('Full bathrooms (shower + sink + toilet)') }}</div>
                        </div>
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Separate toilets') }}</label>
                            <input class="input" type="number" name="toilets" value="{{ old('toilets', $property->toilets) }}" min="0" max="50">
                            <div class="eh-hint">{{ __('Toilet-only / powder rooms') }}</div>
                        </div>
                        @if ($isWholeHouse)
                            <div class="eh-field">
                                <label class="eh-label">{{ __('Max guests') }}</label>
                                <input class="input" type="number" name="max_guests" value="{{ old('max_guests', $singleRoom?->max_adults ?? 2) }}" min="1" max="200">
                                <div class="eh-hint">{{ __('Whole-house sleeping capacity') }}</div>
                            </div>
                        @endif
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Default guests') }}</label>
                            <input class="input" type="number" name="default_guests" value="{{ old('default_guests', $property->default_guests) }}" min="1" max="200" placeholder="{{ __('Auto') }}">
                            <div class="eh-hint">{{ __('Pre-fills the guest stepper on your booking page. Blank = half of max capacity.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Pricing & check-in ─────────────────────────────────── --}}
            <div class="card eh-card">
                <div class="eh-chead">
                    <span class="eh-ico"><x-icon name="wallet" :size="18"/></span>
                    <div style="flex:1; display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                        <div>
                            <div class="eh-ctitle">{{ __('Pricing & check-in') }}</div>
                            <div class="eh-csub">{{ __('The nightly rate and your check-in / check-out times.') }}</div>
                        </div>
                        <span class="pill" style="background:var(--primary-tint); color:var(--primary-deep);">
                            {{ $isWholeHouse ? '🏠 '.__('Whole-house') : '🛏️ '.__('Per-room') }}
                        </span>
                    </div>
                </div>
                <div class="eh-body">
                    {{-- Hidden: mode isn't switchable here (would require rebuilding rooms). --}}
                    <input type="hidden" name="pricing_mode" value="{{ $property->pricing_mode ?? 'whole_house' }}">
                    <div class="eh-grid-3">
                        <div class="eh-field">
                            <label class="eh-label">{{ $isWholeHouse ? __('Price / night (whole house)') : __('Base price / night (per room)') }}</label>
                            <input class="input" type="number" name="base_price" value="{{ old('base_price', number_format($baseRate, 0, '.', '')) }}" min="0" max="999999" step="1" placeholder="220">
                            <div class="eh-hint">
                                {{ $isWholeHouse
                                    ? __('Flat rate for the whole property.')
                                    : __('Applied to all :n room(s) on save.', ['n' => $property->rooms->count()]) }}
                            </div>
                        </div>
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Check-in time') }} <span class="eh-req">*</span></label>
                            <input class="input" type="time" name="check_in_time" value="{{ old('check_in_time', \Illuminate\Support\Str::of($property->check_in_time)->limit(5, '')) }}" required>
                        </div>
                        <div class="eh-field">
                            <label class="eh-label">{{ __('Check-out time') }} <span class="eh-req">*</span></label>
                            <input class="input" type="time" name="check_out_time" value="{{ old('check_out_time', \Illuminate\Support\Str::of($property->check_out_time)->limit(5, '')) }}" required>
                        </div>
                    </div>
                    <p class="eh-hint" style="margin-top:0;">
                        {{ __('The booking fee (pay-now deposit) is set separately under Property → Pricing.') }}
                    </p>
                </div>
            </div>

            {{-- ── Facilities ─────────────────────────────────────────── --}}
            <div class="card eh-card">
                <div class="eh-chead">
                    <span class="eh-ico"><x-icon name="sparkle" :size="18"/></span>
                    <div style="flex:1; display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                        <div>
                            <div class="eh-ctitle">{{ __('Facilities & amenities') }}</div>
                            <div class="eh-csub">{{ __('Tick anything this property offers.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="eh-body">
                    @include('tenant.properties._amenity_picker', [
                        'amenityGroups'      => $amenityGroups,
                        'selectedAmenityIds' => $selectedAmenityIds,
                        'title'              => false,
                    ])
                </div>
            </div>

            {{-- ── Policies ───────────────────────────────────────────── --}}
            <div class="card eh-card">
                <div class="eh-chead">
                    <span class="eh-ico"><x-icon name="lock" :size="18"/></span>
                    <div>
                        <div class="eh-ctitle">{{ __('Policies') }}</div>
                        <div class="eh-csub">{{ __('House rules and your cancellation terms.') }}</div>
                    </div>
                </div>
                <div class="eh-body">
                    <div class="eh-field">
                        <label class="eh-label">{{ __('House rules') }}</label>
                        <textarea class="input" name="house_rules" rows="3" maxlength="1000" placeholder="{{ __('No smoking · Quiet hours 11pm–7am · Respect local customs · Halal-only kitchen') }}" style="height:auto; padding:10px 12px; font-family:inherit; resize:vertical;">{{ old('house_rules', is_string($property->house_rules) ? $property->house_rules : '') }}</textarea>
                    </div>
                    <div class="eh-field">
                        <label class="eh-label">{{ __('Cancellation policy') }}</label>
                        <textarea class="input" name="cancellation_policy" rows="2" maxlength="1000" placeholder="{{ __('Free cancellation up to 7 days before check-in. 50% refund within 7 days. Non-refundable within 48h.') }}" style="height:auto; padding:10px 12px; font-family:inherit; resize:vertical;">{{ old('cancellation_policy', $property->cancellation_policy) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ── Sticky save bar ────────────────────────────────────── --}}
            <div class="eh-actions">
                <a href="{{ route('tenant.settings.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                <div class="eh-actions-right">
                    <a href="{{ route('tenant.properties.show', ['id' => $property->id]) }}" class="btn">{{ __('View detail') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
