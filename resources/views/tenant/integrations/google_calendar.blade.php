<x-app-layout :title="$meta['name']">
    <div style="max-width: 720px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
        <div>
            <a href="{{ route('tenant.integrations.index') }}" style="font-size: 12.5px; color: var(--ink-3); text-decoration: none;">← {{ __('Integrations') }}</a>
            <div style="display: flex; align-items: flex-end; justify-content: space-between; margin-top: 6px;">
                <div>
                    <div class="kicker">{{ __('Provider') }}</div>
                    <h2 class="display-2" style="margin: 4px 0 0;">{{ $meta['name'] }}</h2>
                    <p style="margin: 6px 0 0; color: var(--ink-3); font-size: 14px;">{{ $meta['description'] }}</p>
                </div>
                @if ($record && $record->enabled && ! empty($record->config['calendar_id']))
                    <x-pill variant="ok" :dot="true">{{ __('Connected') }}</x-pill>
                @elseif ($needsPicker)
                    <x-pill variant="warn">{{ __('Choose a calendar') }}</x-pill>
                @else
                    <x-pill>{{ __('Not connected') }}</x-pill>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--bg-warm); color: var(--ink); font-size: 13.5px;">
                {{ session('status') }}
            </div>
        @endif

        @if ($needsPicker)
            {{-- =========================================================
                 PICKER STATE — choose which calendar bookings sync to
                 ========================================================= --}}
            <div class="hauz-card" style="padding: 28px;">
                <div style="display: flex; align-items: center; gap: 14px; padding-bottom: 18px; border-bottom: .5px solid var(--line); margin-bottom: 22px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #4285F4, #34A853); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; font-weight: 600; font-size: 13px;">G</div>
                    <div style="flex: 1;">
                        <div style="font-weight: 500; font-size: 15px; color: var(--ink);">{{ __('Connected as :email', ['email' => $record->config['google_email'] ?? __('your Google account')]) }}</div>
                        <div style="font-size: 12.5px; color: var(--ink-3); margin-top: 2px;">{{ __('Pick which calendar should hold your bookings.') }}</div>
                    </div>
                </div>

                @if ($pickerError)
                    <div style="padding: 14px 16px; background: var(--err-tint, #f9e4e0); border-radius: 8px; font-size: 13px; color: var(--err, #b94a3a); margin-bottom: 16px;">
                        <strong>{{ __('Could not load your calendars') }}:</strong> {{ $pickerError }}
                        <div style="margin-top: 6px;">
                            <a href="{{ route('oauth.google.start') }}" style="color: var(--err, #b94a3a); text-decoration: underline; font-size: 12.5px;">{{ __('Reconnect Google') }}</a>
                        </div>
                    </div>
                @endif

                @if (! $pickerError && is_array($calendars))
                    <form method="POST" action="{{ route('tenant.integrations.google_calendar.select') }}" style="display: flex; flex-direction: column; gap: 8px;">
                        @csrf

                        @php
                            // Preselect logic:
                            //   - if user has an existing calendar_id (editing), select that
                            //   - else select the primary
                            $selectedId = $record->config['calendar_id'] ?? null;
                            if (! $selectedId) {
                                foreach ($calendars as $c) {
                                    if ($c['primary'] ?? false) { $selectedId = $c['id']; break; }
                                }
                                if (! $selectedId && count($calendars) > 0) $selectedId = $calendars[0]['id'];
                            }
                        @endphp

                        <div class="kicker" style="margin-bottom: 8px;">{{ __('Your calendars') }}</div>

                        <div style="display: flex; flex-direction: column; gap: 6px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
                            @foreach ($calendars as $cal)
                                @php
                                    $isPrimary = $cal['primary'] ?? false;
                                    $color = $cal['backgroundColor'] ?? '#7a9bbf';
                                    $checked = $cal['id'] === $selectedId;
                                @endphp
                                <label style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid {{ $checked ? 'var(--primary, #2596c6)' : 'var(--line)' }}; border-radius: 10px; background: {{ $checked ? 'var(--primary-tint, #e0eff7)' : '#fff' }}; cursor: pointer; transition: all .15s ease;">
                                    <input type="radio" name="picker_choice" value="existing-{{ $loop->index }}"
                                           data-cal-id="{{ $cal['id'] }}"
                                           data-cal-name="{{ $cal['summary'] ?? $cal['id'] }}"
                                           {{ $checked ? 'checked' : '' }}
                                           onchange="document.getElementById('calendar_id').value = this.dataset.calId; document.getElementById('calendar_name').value = this.dataset.calName; document.getElementById('create_new').value = ''; updatePickerStyles();"
                                           style="accent-color: var(--primary, #2596c6);">
                                    <span style="display: inline-block; width: 14px; height: 14px; border-radius: 3px; background: {{ $color }}; flex-shrink: 0;"></span>
                                    <span style="flex: 1; font-size: 14px; color: var(--ink); font-weight: 500;">{{ $cal['summary'] ?? $cal['id'] }}</span>
                                    @if ($isPrimary)
                                        <span style="font-size: 10px; font-family: var(--font-mono, monospace); color: var(--primary-deep, #14587f); background: rgba(37, 150, 198, 0.12); padding: 2px 8px; border-radius: 999px; letter-spacing: .05em;">{{ __('PRIMARY') }}</span>
                                    @endif
                                </label>
                            @endforeach

                            {{-- Create-new card --}}
                            <label style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px dashed var(--line); border-radius: 10px; background: var(--bg-warm); cursor: pointer; transition: all .15s ease;" data-create-card>
                                <input type="radio" name="picker_choice" value="create-new"
                                       onchange="document.getElementById('create_new').value = '1'; document.getElementById('calendar_id').value = ''; document.getElementById('calendar_name').value = ''; updatePickerStyles();"
                                       style="accent-color: var(--primary, #2596c6);">
                                <span style="display: inline-block; width: 14px; height: 14px; border-radius: 3px; background: linear-gradient(135deg, var(--primary, #2596c6), var(--secondary, #2cb8c4)); flex-shrink: 0;"></span>
                                <span style="flex: 1; font-size: 14px; color: var(--ink); font-weight: 500;">
                                    {{ __('Create a new "Tempahlah Bookings" calendar') }}
                                    <span style="display: block; font-size: 11.5px; color: var(--ink-3); font-weight: 400; margin-top: 2px;">{{ __('Keeps your homestay schedule separate from personal events.') }}</span>
                                </span>
                            </label>
                        </div>

                        {{-- Hidden state mirrored by the radios above --}}
                        <input type="hidden" id="calendar_id"   name="calendar_id"   value="{{ $selectedId }}">
                        <input type="hidden" id="calendar_name" name="calendar_name" value="{{ collect($calendars)->firstWhere('id', $selectedId)['summary'] ?? '' }}">
                        <input type="hidden" id="create_new"    name="create_new"    value="">

                        <div style="display: flex; gap: 8px; justify-content: flex-end; padding-top: 18px; margin-top: 6px; border-top: .5px solid var(--line);">
                            @if (! empty($record->config['calendar_id']))
                                <a href="{{ route('tenant.integrations.show', 'google_calendar') }}" class="btn">{{ __('Cancel') }}</a>
                            @endif
                            <button type="submit" class="btn btn-primary">{{ __('Save calendar choice') }}</button>
                        </div>
                    </form>
                @endif
            </div>

            <script>
                function updatePickerStyles() {
                    document.querySelectorAll('input[name="picker_choice"]').forEach(function (r) {
                        const label = r.closest('label');
                        if (! label) return;
                        if (r.checked && r.value !== 'create-new') {
                            label.style.borderColor = 'var(--primary, #2596c6)';
                            label.style.background  = 'var(--primary-tint, #e0eff7)';
                        } else if (r.checked && r.value === 'create-new') {
                            label.style.borderColor = 'var(--primary, #2596c6)';
                            label.style.background  = 'var(--primary-tint, #e0eff7)';
                            label.style.borderStyle = 'solid';
                        } else {
                            label.style.borderColor = 'var(--line)';
                            label.style.background  = r.value === 'create-new' ? 'var(--bg-warm)' : '#fff';
                            if (r.value === 'create-new') label.style.borderStyle = 'dashed';
                        }
                    });
                }
            </script>

        @elseif ($record && $record->enabled && ! empty($record->config['access_token']) && ! empty($record->config['calendar_id']))
            {{-- =========================================================
                 CONNECTED STATE — fully connected with chosen calendar
                 ========================================================= --}}
            <div class="hauz-card" style="padding: 28px; display: flex; flex-direction: column; gap: 20px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #4285F4, #34A853); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="22" height="22" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#FFFFFF" d="M37,9H11c-1.7,0-3,1.3-3,3v24c0,1.7,1.3,3,3,3h26c1.7,0,3-1.3,3-3V12C40,10.3,38.7,9,37,9z M37,36H11V18h26V36z"/>
                            <rect x="11" y="9" width="26" height="6" fill="#1A73E8"/>
                            <text x="24" y="32" text-anchor="middle" fill="#1A73E8" font-family="Arial, sans-serif" font-weight="700" font-size="13">{{ now()->day }}</text>
                        </svg>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 500; font-size: 15px; color: var(--ink);">{{ __('Connected as :email', ['email' => $record->config['google_email'] ?? __('your Google account')]) }}</div>
                        <div style="font-size: 12.5px; color: var(--ink-3); margin-top: 2px;">
                            {{ __('Syncing to') }}: <strong>{{ $record->config['calendar_name'] ?? __('Primary calendar') }}</strong>
                            @if (! empty($record->connected_at))
                                · {{ __('connected') }} {{ $record->connected_at->diffForHumans() }}
                            @endif
                        </div>
                    </div>
                </div>

                @if (! empty($record->config['last_error']))
                    <div style="padding: 12px 14px; background: var(--err-tint, #f9e4e0); border-radius: 8px; font-size: 13px; color: var(--err, #b94a3a);">
                        <strong>{{ __('Warning') }}:</strong> {{ $record->config['last_error'] }}
                        <div style="margin-top: 4px; opacity: 0.8;">{{ __('Disconnect and reconnect to re-authorize.') }}</div>
                    </div>
                @endif

                <div style="display: flex; gap: 8px; padding-top: 14px; border-top: .5px solid var(--line); flex-wrap: wrap;">
                    <a href="{{ route('tenant.integrations.show', ['provider' => 'google_calendar', 'edit' => 1]) }}" class="btn btn-sm">{{ __('Change calendar') }}</a>
                    <a href="{{ route('oauth.google.start') }}" class="btn btn-sm">{{ __('Reconnect') }}</a>
                    <form method="POST" action="{{ route('tenant.integrations.disconnect', 'google_calendar') }}"
                          onsubmit="return confirm('{{ __('Disconnect Google Calendar? Your bookings will stop syncing.') }}');"
                          style="margin-left: auto;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--err, #b94a3a);">{{ __('Disconnect') }}</button>
                    </form>
                </div>
            </div>

            <div style="font-size: 11.5px; color: var(--ink-3); padding: 0 8px; line-height: 1.6;">
                {{ __('Confirmed bookings on your dashboard will appear as all-day events on this calendar. Events created elsewhere will block those dates in Tempahlah (two-way sync rollout coming).') }}
            </div>
        @else
            {{-- =========================================================
                 DISCONNECTED STATE — "Connect Google Calendar" CTA
                 ========================================================= --}}
            <div class="hauz-card" style="padding: 36px 28px; display: flex; flex-direction: column; align-items: center; gap: 22px; text-align: center;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <svg width="40" height="40" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#4285F4" d="M24,9.5c3.54,0,6.71,1.22,9.21,3.6l6.85-6.85C35.9,2.38,30.47,0,24,0C14.62,0,6.51,5.38,2.56,13.22l7.98,6.19 C12.43,13.72,17.74,9.5,24,9.5z"/>
                        <path fill="#34A853" d="M46.98,24.55c0-1.57-0.15-3.09-0.38-4.55H24v9.02h12.94c-0.58,2.96-2.26,5.48-4.78,7.18l7.73,6 c4.51-4.18,7.09-10.36,7.09-17.65z"/>
                        <path fill="#FBBC05" d="M10.53,28.59c-0.48-1.45-0.76-2.99-0.76-4.59s0.27-3.14,0.76-4.59l-7.98-6.19C0.92,16.46,0,20.12,0,24 c0,3.88,0.92,7.54,2.56,10.78L10.53,28.59z"/>
                        <path fill="#EA4335" d="M24,48c6.48,0,11.93-2.13,15.89-5.81l-7.73-6c-2.15,1.45-4.92,2.3-8.16,2.3c-6.26,0-11.57-4.22-13.47-9.91 l-7.98,6.19C6.51,42.62,14.62,48,24,48z"/>
                    </svg>
                    <span style="font-size: 22px; color: var(--ink-3); font-weight: 200;">+</span>
                    <img src="/icons/logo.svg" alt="" style="width: 40px; height: 40px;">
                </div>

                <div>
                    <h3 style="font-family: var(--font-display, Georgia, serif); font-weight: 500; font-size: 24px; letter-spacing: -0.015em; margin: 0; color: var(--ink);">
                        {{ __('One-click sync with Google Calendar') }}
                    </h3>
                    <p style="margin: 8px 0 0; color: var(--ink-2); font-size: 14.5px; line-height: 1.55; max-width: 440px;">
                        {{ __('Connect your Google account, pick which calendar should hold your bookings, and you\'re done. No client IDs, no copy-paste — just click.') }}
                    </p>
                </div>

                <a href="{{ route('oauth.google.start') }}"
                   class="btn btn-primary btn-lg"
                   style="text-decoration: none; display: inline-flex; align-items: center; gap: 10px; padding: 14px 24px; font-size: 15px;">
                    <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#FFFFFF" d="M24,9.5c3.54,0,6.71,1.22,9.21,3.6l6.85-6.85C35.9,2.38,30.47,0,24,0C14.62,0,6.51,5.38,2.56,13.22l7.98,6.19 C12.43,13.72,17.74,9.5,24,9.5z" opacity="0.92"/>
                        <path fill="#FFFFFF" d="M46.98,24.55c0-1.57-0.15-3.09-0.38-4.55H24v9.02h12.94c-0.58,2.96-2.26,5.48-4.78,7.18l7.73,6 c4.51-4.18,7.09-10.36,7.09-17.65z" opacity="0.92"/>
                        <path fill="#FFFFFF" d="M10.53,28.59c-0.48-1.45-0.76-2.99-0.76-4.59s0.27-3.14,0.76-4.59l-7.98-6.19C0.92,16.46,0,20.12,0,24 c0,3.88,0.92,7.54,2.56,10.78L10.53,28.59z" opacity="0.92"/>
                        <path fill="#FFFFFF" d="M24,48c6.48,0,11.93-2.13,15.89-5.81l-7.73-6c-2.15,1.45-4.92,2.3-8.16,2.3c-6.26,0-11.57-4.22-13.47-9.91 l-7.98,6.19C6.51,42.62,14.62,48,24,48z" opacity="0.92"/>
                    </svg>
                    {{ __('Connect Google Calendar') }}
                </a>

                <ul style="list-style: none; padding: 0; margin: 6px 0 0; display: flex; flex-direction: column; gap: 8px; text-align: left; max-width: 380px; width: 100%;">
                    <li style="display: flex; gap: 10px; font-size: 13px; color: var(--ink-2);">
                        <span style="color: var(--primary, #2596c6); flex-shrink: 0;">✓</span>
                        {{ __('Pick from your existing calendars — or auto-create a dedicated one') }}
                    </li>
                    <li style="display: flex; gap: 10px; font-size: 13px; color: var(--ink-2);">
                        <span style="color: var(--primary, #2596c6); flex-shrink: 0;">✓</span>
                        {{ __('Confirmed bookings appear on your calendar instantly') }}
                    </li>
                    <li style="display: flex; gap: 10px; font-size: 13px; color: var(--ink-2);">
                        <span style="color: var(--primary, #2596c6); flex-shrink: 0;">✓</span>
                        {{ __('Disconnect any time — your data stays with you') }}
                    </li>
                </ul>
            </div>

            <div style="font-size: 11.5px; color: var(--ink-3); padding: 0 8px; line-height: 1.6;">
                {{ __('You may see a "Google hasn\'t verified this app" warning while we complete Google\'s verification process. This is expected during early access — click "Advanced" then "Continue" to proceed. The connection is still fully secure.') }}
            </div>
        @endif
    </div>
</x-app-layout>
