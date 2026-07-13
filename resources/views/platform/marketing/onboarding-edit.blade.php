<x-app-layout :title="__('Onboarding step :n', ['n' => $step->step_no])" :breadcrumbs="[__('Platform'), __('Email marketing')]">
    <div style="max-width: 760px; margin: 0 auto; display:flex; flex-direction:column; gap: 18px;">

        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Onboarding series') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Step :n', ['n' => $step->step_no]) }} · {{ __('day') }} +{{ $step->day_offset }}</div>
            </div>
            <a href="{{ route('platform.marketing.index') }}" class="btn btn-sm">
                <x-icon name="arrow-left" :size="12"/> {{ __('Back') }}
            </a>
        </div>

        @if ($errors->any())
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); color: var(--err);">{{ $errors->first() }}</div>
        @endif
        @if (session('status'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--ok); background: var(--ok-tint);">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err);">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('platform.marketing.onboarding.update', $step) }}"
              style="display:flex; flex-direction:column; gap: 18px;">
            @csrf
            @method('PATCH')

            <div class="hauz-card" style="padding: 20px; display:flex; flex-direction:column; gap: 14px;">
                <label style="display:flex; flex-direction:column; gap: 5px;">
                    <span style="font-size: 12px; color: var(--ink-2);">{{ __('Subject') }}</span>
                    <input type="text" name="subject" required maxlength="200" class="input"
                           value="{{ old('subject', $step->subject) }}">
                </label>

                <div style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px;">
                    <label style="display:flex; flex-direction:column; gap: 5px;">
                        <span style="font-size: 12px; color: var(--ink-2);">{{ __('Days after signup') }}</span>
                        <input type="number" name="day_offset" required min="0" max="60" class="input"
                               value="{{ old('day_offset', $step->day_offset) }}">
                    </label>
                    <label style="display:flex; align-items:center; gap: 8px; padding-top: 20px;">
                        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $step->enabled))>
                        <span style="font-size: 12.5px;">{{ __('Enabled') }}</span>
                    </label>
                    <label style="display:flex; align-items:center; gap: 8px; padding-top: 20px;">
                        <input type="checkbox" name="skip_if_paid" value="1" @checked(old('skip_if_paid', $step->skip_if_paid))>
                        <span style="font-size: 12.5px;">{{ __('Skip if already paid') }}</span>
                    </label>
                </div>

                <label style="display:flex; flex-direction:column; gap: 5px;">
                    <span style="font-size: 12px; color: var(--ink-2);">{{ __('Email body (Markdown)') }}</span>
                    <textarea name="body_md" required maxlength="20000" rows="18" class="input"
                              style="font-family: var(--font-mono); font-size: 12.5px; line-height: 1.55; height: auto;">{{ old('body_md', $step->body_md) }}</textarea>
                    <span style="font-size: 11.5px; color: var(--ink-3);">
                        {{ __('Tokens:') }} <code>{name}</code> · <code>{business_name}</code> · <code>{upgrade_url}</code> · <code>{booking_url}</code>.
                        {{ __('An unsubscribe link is added to the footer automatically.') }}
                    </span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap: 8px;">
                <button type="submit" class="btn btn-primary">{{ __('Save step') }}</button>
            </div>
        </form>

        <form method="POST" action="{{ route('platform.marketing.onboarding.test', $step) }}">
            @csrf
            <button type="submit" class="btn btn-sm">
                <x-icon name="mail" :size="13"/> {{ __('Send test to me') }}
            </button>
        </form>
    </div>
</x-app-layout>
