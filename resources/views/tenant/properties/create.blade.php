<x-app-layout :title="__('Add property')">
    <div style="max-width: 640px; margin: 0 auto; display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.properties.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Back to properties') }}</a>
            <h2 class="display-2" style="margin: 8px 0 0;">{{ __('Add a property') }}</h2>
            <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">
                {{ __('Required fields are marked. You can edit everything later.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('tenant.properties.store') }}" class="hauz-card" style="padding: 22px; display:flex; flex-direction:column; gap: 16px;">
            @csrf
            <div>
                <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Name') }} *</label>
                <input type="text" name="name" required style="width:100%; height:38px; padding: 0 12px; border:.5px solid var(--line-2); border-radius: var(--r-md); font-size:13.5px; background: var(--bg-elev);">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('City') }}</label>
                    <input type="text" name="city" style="width:100%; height:38px; padding: 0 12px; border:.5px solid var(--line-2); border-radius: var(--r-md); font-size:13.5px; background: var(--bg-elev);">
                </div>
                <div>
                    <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Bedrooms') }}</label>
                    <input type="number" name="bedrooms" min="1" value="1" style="width:100%; height:38px; padding: 0 12px; border:.5px solid var(--line-2); border-radius: var(--r-md); font-size:13.5px; background: var(--bg-elev);">
                </div>
            </div>
            <div>
                <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Base price (RM/night)') }} *</label>
                <input type="number" name="base_price" required min="0" style="width:100%; height:38px; padding: 0 12px; border:.5px solid var(--line-2); border-radius: var(--r-md); font-size:13.5px; background: var(--bg-elev);">
            </div>
            <div>
                <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Description') }}</label>
                <textarea name="description" rows="4" style="width:100%; padding: 10px 12px; border:.5px solid var(--line-2); border-radius: var(--r-md); font-size:13.5px; background: var(--bg-elev); resize: vertical;"></textarea>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end; padding-top: 6px;">
                <a href="{{ route('tenant.properties.index') }}" class="btn">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Create property') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
