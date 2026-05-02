<x-app-layout :title="$meta['name']">
    <div style="max-width: 640px; margin: 0 auto; display:flex; flex-direction:column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.integrations.index') }}" style="font-size:12.5px; color: var(--ink-3); text-decoration:none;">← {{ __('Integrations') }}</a>
            <div style="display:flex; align-items:flex-end; justify-content:space-between; margin-top: 6px;">
                <div>
                    <div class="kicker">{{ __('Provider') }}</div>
                    <h2 class="display-2" style="margin: 4px 0 0;">{{ $meta['name'] }}</h2>
                    <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">{{ $meta['description'] }}</p>
                </div>
                @if ($record->exists && $record->enabled)
                    <x-pill variant="ok" :dot="true">{{ __('Connected') }}</x-pill>
                @else
                    <x-pill>{{ __('Not connected') }}</x-pill>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <ul style="margin:0; padding-left: 18px;">@foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tenant.integrations.update', $provider) }}" class="hauz-card" style="padding: 22px; display:flex; flex-direction:column; gap: 16px;">
            @csrf
            @method('PATCH')

            @foreach ($meta['fields'] as $key => $field)
                @php
                    $current = old($key, $record->config[$key] ?? '');
                @endphp
                <div>
                    <label class="kicker" style="display:block; margin-bottom: 6px;">{{ $field['label'] }}</label>
                    @if ($field['type'] === 'checkbox')
                        <label style="display:inline-flex; align-items:center; gap: 8px; padding-top: 4px; cursor: pointer; font-size: 13.5px;">
                            <input type="checkbox" name="{{ $key }}" value="1" {{ $current ? 'checked' : '' }}>
                            <span>{{ __('Enabled') }}</span>
                        </label>
                    @else
                        <input class="input"
                               type="{{ $field['type'] }}"
                               name="{{ $key }}"
                               value="{{ $current }}"
                               placeholder="{{ $field['placeholder'] ?? '' }}"
                               autocomplete="off">
                    @endif
                </div>
            @endforeach

            <div style="border-top: .5px solid var(--line); padding-top: 14px;">
                <label style="display:inline-flex; align-items:center; gap: 8px; cursor: pointer; font-size: 13.5px;">
                    <input type="checkbox" name="enabled" value="1" {{ $record->enabled ? 'checked' : '' }}>
                    <span>{{ __('Enable this integration') }}</span>
                </label>
                <div style="font-size: 11.5px; color: var(--ink-3); margin-top: 4px; padding-left: 22px;">
                    {{ __('When disabled, credentials are kept but the integration is paused.') }}
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap: 8px; padding-top: 6px;">
                <a href="{{ route('tenant.integrations.index') }}" class="btn">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ $record->exists ? __('Save changes') : __('Connect') }}</button>
            </div>
        </form>

        @if ($record->exists)
            <form method="POST" action="{{ route('tenant.integrations.disconnect', $provider) }}"
                  onsubmit="return confirm('{{ __('Disconnect and clear credentials?') }}');"
                  style="text-align: right;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost" style="color: var(--err); font-size: 12px;">{{ __('Disconnect') }}</button>
            </form>
        @endif

        <div style="font-size: 11.5px; color: var(--ink-3); padding: 0 8px;">
            {{ __('Credentials are encrypted at rest using your application key. Never shared with third parties beyond the integration provider itself.') }}
        </div>
    </div>
</x-app-layout>
