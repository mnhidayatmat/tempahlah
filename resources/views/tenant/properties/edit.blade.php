<x-app-layout :title="__('Edit :name', ['name' => $property->name])" :breadcrumbs="[__('Settings'), __('Edit')]">
    <div style="max-width: 760px; margin: 0 auto; display:flex; flex-direction:column; gap: 20px;">

        <div>
            <a href="{{ route('tenant.settings.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Back to settings') }}</a>
            <h2 class="display-2" style="margin: 8px 0 0;">{{ __('Edit homestay') }}</h2>
            <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">
                {{ $property->name }} · <span style="font-family: var(--font-mono);">{{ $property->public_id }}</span>
            </p>
        </div>

        @if ($errors->any())
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <strong style="display:block; margin-bottom: 6px;">{{ __('Please fix the following:') }}</strong>
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--ok-tint); color: var(--ok); border-color: var(--ok); font-size: 13px;">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('tenant.properties.update', ['property' => $property->public_id]) }}" style="display:flex; flex-direction:column; gap: 18px;">
            @csrf
            @method('PATCH')

            {{-- Basic info --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Basic info') }}</div>
                <div style="display:grid; grid-template-columns: 1fr; gap: 14px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Homestay name') }} *</label>
                        <input class="input" type="text" name="name" value="{{ old('name', $property->name) }}" required maxlength="120">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Status') }} *</label>
                        <select class="input" name="status">
                            <option value="draft"    {{ old('status', $property->status) === 'draft'    ? 'selected' : '' }}>{{ __('Draft (hidden from public booking page)') }}</option>
                            <option value="active"   {{ old('status', $property->status) === 'active'   ? 'selected' : '' }}>{{ __('Active (visible on public booking page)') }}</option>
                            <option value="archived" {{ old('status', $property->status) === 'archived' ? 'selected' : '' }}>{{ __('Archived (no new bookings)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Description (English)') }}</label>
                        <textarea class="input" name="description_en" rows="3" maxlength="2000" style="font-family: inherit;">{{ old('description_en', $property->description_en) }}</textarea>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Description (BM)') }}</label>
                        <textarea class="input" name="description_bm" rows="3" maxlength="2000" style="font-family: inherit;">{{ old('description_bm', $property->description_bm) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Address') }}</div>
                <div style="display:grid; grid-template-columns: 1fr; gap: 14px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Address line 1') }} *</label>
                        <input class="input" type="text" name="address_line1" value="{{ old('address_line1', $property->address_line1) }}" required maxlength="160">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Address line 2') }}</label>
                        <input class="input" type="text" name="address_line2" value="{{ old('address_line2', $property->address_line2) }}" maxlength="160">
                    </div>
                    <div style="display:grid; grid-template-columns: 2fr 2fr 1fr; gap: 12px;">
                        <div>
                            <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('City') }}</label>
                            <input class="input" type="text" name="city" value="{{ old('city', $property->city) }}" maxlength="80">
                        </div>
                        <div>
                            <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('State') }}</label>
                            <input class="input" type="text" name="state" value="{{ old('state', $property->state) }}" maxlength="80">
                        </div>
                        <div>
                            <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Postcode') }}</label>
                            <input class="input" type="text" name="postcode" value="{{ old('postcode', $property->postcode) }}" maxlength="16">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stay logistics --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Stay logistics') }}</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Check-in time') }} *</label>
                        <input class="input" type="time" name="check_in_time" value="{{ old('check_in_time', \Illuminate\Support\Str::of($property->check_in_time)->limit(5, '')) }}" required>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Check-out time') }} *</label>
                        <input class="input" type="time" name="check_out_time" value="{{ old('check_out_time', \Illuminate\Support\Str::of($property->check_out_time)->limit(5, '')) }}" required>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Base rate (RM / night)') }}</label>
                        <input class="input" type="number" name="base_price" value="{{ old('base_price', number_format($baseRate, 0, '.', '')) }}" min="0" max="999999" step="1" placeholder="220">
                        <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('Applied to all :n room(s) on save.', ['n' => $property->rooms->count()]) }}</div>
                    </div>
                </div>
            </div>

            {{-- Policies --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Policies') }}</div>
                <div style="display:grid; grid-template-columns: 1fr; gap: 14px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('House rules') }}</label>
                        <textarea class="input" name="house_rules" rows="3" maxlength="1000" placeholder="{{ __('No smoking · Quiet hours 11pm–7am · Respect local customs · Halal-only kitchen') }}" style="font-family: inherit;">{{ old('house_rules', is_string($property->house_rules) ? $property->house_rules : '') }}</textarea>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Cancellation policy') }}</label>
                        <textarea class="input" name="cancellation_policy" rows="2" maxlength="1000" placeholder="{{ __('Free cancellation up to 7 days before check-in. 50% refund within 7 days. Non-refundable within 48h.') }}" style="font-family: inherit;">{{ old('cancellation_policy', $property->cancellation_policy) }}</textarea>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content: space-between; align-items: center; gap: 12px;">
                <a href="{{ route('tenant.settings.index') }}" class="btn">{{ __('Cancel') }}</a>
                <div style="display:flex; gap: 8px;">
                    <a href="{{ route('tenant.properties.show', ['id' => $property->id]) }}" class="btn">{{ __('View detail') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
