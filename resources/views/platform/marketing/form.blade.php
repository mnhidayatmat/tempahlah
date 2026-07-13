<x-app-layout :title="$campaign ? __('Edit campaign') : __('New campaign')" :breadcrumbs="[__('Platform'), __('Email marketing')]">
    <div style="max-width: 760px; margin: 0 auto; display:flex; flex-direction:column; gap: 18px;">

        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker" style="color: var(--primary);">{{ __('Email marketing') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ $campaign ? __('Edit campaign') : __('New campaign') }}</div>
            </div>
            <a href="{{ $campaign ? route('platform.marketing.show', $campaign) : route('platform.marketing.index') }}" class="btn btn-sm">
                <x-icon name="arrow-left" :size="12"/> {{ __('Back') }}
            </a>
        </div>

        @if ($errors->any())
            <div class="hauz-card" style="padding: 14px 16px; border-color: var(--err); color: var(--err);">{{ $errors->first() }}</div>
        @endif

        <form method="POST"
              action="{{ $campaign ? route('platform.marketing.update', $campaign) : route('platform.marketing.store') }}"
              style="display:flex; flex-direction:column; gap: 18px;">
            @csrf
            @if ($campaign) @method('PATCH') @endif

            <div class="hauz-card" style="padding: 20px; display:flex; flex-direction:column; gap: 14px;">
                <label style="display:flex; flex-direction:column; gap: 5px;">
                    <span style="font-size: 12px; color: var(--ink-2);">{{ __('Subject') }}</span>
                    <input type="text" name="subject" required maxlength="200" class="input"
                           value="{{ old('subject', $defaults['subject']) }}">
                </label>

                <label style="display:flex; flex-direction:column; gap: 5px;">
                    <span style="font-size: 12px; color: var(--ink-2);">{{ __('Audience') }}</span>
                    <select name="audience" class="input">
                        @foreach (\App\Models\MarketingCampaign::AUDIENCES as $key => $label)
                            <option value="{{ $key }}" @selected(old('audience', $defaults['audience']) === $key)>
                                {{ __($label) }} ({{ $audienceCounts[$key] ?? 0 }} {{ __('reachable') }})
                            </option>
                        @endforeach
                    </select>
                    <span style="font-size: 11.5px; color: var(--ink-3);">
                        {{ __('Counts exclude suspended tenants, unsubscribed hosts and tenants with no email on file. The list is frozen when you press Send.') }}
                    </span>
                </label>

                <label style="display:flex; flex-direction:column; gap: 5px;">
                    <span style="font-size: 12px; color: var(--ink-2);">{{ __('Email body (Markdown)') }}</span>
                    <textarea name="body_md" required maxlength="20000" rows="18" class="input"
                              style="font-family: var(--font-mono); font-size: 12.5px; line-height: 1.55; height: auto;">{{ old('body_md', $defaults['body_md']) }}</textarea>
                    <span style="font-size: 11.5px; color: var(--ink-3);">
                        {{ __('Personalization tokens:') }}
                        <code>{name}</code> · <code>{business_name}</code> · <code>{upgrade_url}</code>.
                        {{ __('Markdown works: **bold**, - lists, [links](https://…). An unsubscribe link is added to the footer automatically.') }}
                    </span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap: 8px;">
                <button type="submit" class="btn btn-primary">
                    {{ $campaign ? __('Save changes') : __('Save draft') }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
