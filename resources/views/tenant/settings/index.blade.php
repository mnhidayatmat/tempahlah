<x-app-layout :title="__('Settings')">
    <div style="display:flex; flex-direction:column; gap: 20px; max-width: 880px;">

        {{-- Header --}}
        <div>
            <div class="kicker">{{ __('Workspace') }}</div>
            <div class="display-2" style="margin-top: 4px;">{{ __('Settings') }}</div>
            <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                {{ __('Tenant business info, taxes, MOTAC license and locale defaults.') }}
            </div>
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
        @if (session('error'))
            <div class="hauz-card" style="padding: 12px 16px; background: var(--err-tint); color: var(--err); border-color: var(--err); font-size: 13px;">{{ session('error') }}</div>
        @endif

        {{-- ─── Your homestays ─────────────────────────────────────── --}}
        <div class="hauz-card" style="padding: 22px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 14px; gap: 12px;">
                <div>
                    <div class="kicker">{{ __('Your homestays') }}</div>
                    <div style="margin-top: 2px; font-size: 12.5px; color: var(--ink-3);">
                        {{ trans_choice('{0} No properties created yet.|{1} :count property|[2,*] :count properties', $properties->count(), ['count' => $properties->count()]) }}
                    </div>
                </div>
                <a href="{{ route('tenant.properties.create') }}" class="btn btn-sm btn-primary">
                    <x-icon name="plus" :size="12"/> {{ __('Add homestay') }}
                </a>
            </div>

            @if ($properties->isEmpty())
                <div style="padding: 24px; text-align: center; color: var(--ink-3); font-size: 13px; background: var(--bg-elev); border-radius: var(--r-md);">
                    {{ __('You have not created any homestays yet.') }}
                </div>
            @else
                <div style="display:flex; flex-direction:column; gap: 8px;">
                    @foreach ($properties as $p)
                        @php
                            $startRate = (float) ($p->rooms->min('base_price') ?? 0);
                            $statusCls = match($p->status){ 'active' => 'pill-ok', 'archived' => 'pill-warn', default => '' };
                        @endphp
                        <div style="display:grid; grid-template-columns: 1fr auto; align-items:center; gap: 12px; padding: 14px 16px; background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--r-md);">
                            <div style="min-width: 0;">
                                <div style="display:flex; align-items:center; gap: 10px; flex-wrap: wrap;">
                                    <a href="{{ route('tenant.properties.show', ['id' => $p->id]) }}" style="font-weight: 600; color: var(--ink); text-decoration: none; font-size: 14.5px; letter-spacing: -0.01em;">{{ $p->name }}</a>
                                    <span class="pill {{ $statusCls }}" style="height: 18px; font-size: 10.5px;"><span class="pill-dot"></span>{{ ucfirst($p->status) }}</span>
                                </div>
                                <div style="margin-top: 4px; font-size: 12px; color: var(--ink-3); display:flex; gap: 10px; flex-wrap: wrap; align-items: baseline;">
                                    @if ($p->city)<span>📍 {{ $p->city }}{{ $p->state ? ', '.$p->state : '' }}</span><span style="color: var(--ink-4);">·</span>@endif
                                    <span>{{ $p->rooms->count() }} {{ trans_choice('room|rooms', $p->rooms->count()) }}</span>
                                    <span style="color: var(--ink-4);">·</span>
                                    <span style="font-family: var(--font-mono);">RM {{ number_format($startRate, 0) }}/{{ __('night') }}</span>
                                </div>
                            </div>
                            <div style="display:flex; gap: 6px; align-items: center;">
                                <a href="{{ route('tenant.properties.edit', ['property' => $p->public_id]) }}" class="btn btn-sm" title="{{ __('Edit homestay') }}">
                                    <x-icon name="cog" :size="12"/> {{ __('Edit') }}
                                </a>
                                <form method="POST" action="{{ route('tenant.properties.destroy', ['property' => $p->public_id]) }}" style="display:inline;"
                                      onsubmit="return confirm('{{ __('Delete :name? This cannot be undone if any bookings reference it.', ['name' => addslashes($p->name)]) }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm" title="{{ __('Delete homestay') }}" style="color: var(--err); border-color: var(--err); background: transparent;">
                                        <x-icon name="x" :size="12"/> {{ __('Delete') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('tenant.settings.update') }}" style="display:flex; flex-direction:column; gap: 18px;">
            @csrf
            @method('PATCH')

            {{-- Business info --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Business info') }}</div>
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Business name') }} *</label>
                        <input class="input" type="text" name="business_name" value="{{ old('business_name', $tenant->business_name) }}" required maxlength="120">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('SSM number') }}</label>
                        <input class="input" type="text" name="ssm_number" value="{{ old('ssm_number', $tenant->ssm_number) }}" maxlength="32">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Business email') }} *</label>
                        <input class="input" type="email" name="business_email" value="{{ old('business_email', $tenant->business_email) }}" required maxlength="160">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Business phone') }}</label>
                        <input class="input" type="text" name="business_phone" value="{{ old('business_phone', $tenant->business_phone) }}" maxlength="32" placeholder="+60 12-345 6789">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Owner') }}</label>
                        <input class="input" type="text" value="{{ $tenant->owner?->name ?? '—' }}" disabled style="opacity: .7;">
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Slug') }}</label>
                        <input class="input" type="text" value="{{ $tenant->slug }}" disabled style="opacity: .7; font-family: var(--font-mono);">
                    </div>
                </div>
            </div>

            {{-- Taxes & licensing --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Malaysia tax & licensing') }}</div>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 14px; align-items: flex-start;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('SST registered') }}</label>
                        <label style="display:inline-flex; align-items:center; gap: 8px; padding-top: 8px; cursor:pointer; font-size: 13.5px;">
                            <input type="checkbox" name="sst_registered" value="1" {{ old('sst_registered', $tenant->sst_registered) ? 'checked' : '' }}>
                            <span>{{ __('Charge SST on bookings') }}</span>
                        </label>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('SST rate (decimal)') }}</label>
                        <input class="input" type="number" name="sst_rate" value="{{ old('sst_rate', number_format((float) $tenant->sst_rate, 4, '.', '')) }}" min="0" max="1" step="0.0001" placeholder="0.08">
                        <div style="font-size: 11px; color: var(--ink-3); margin-top: 4px;">{{ __('e.g. 0.08 = 8%') }}</div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('MOTAC license') }}</label>
                        <input class="input" type="text" name="motac_license" value="{{ old('motac_license', $tenant->motac_license) }}" maxlength="64" placeholder="MOT/A/B/C/123">
                        @if ($tenant->motac_license)
                            <div style="margin-top: 6px;">
                                @if ($tenant->motac_verified_at)
                                    <span class="pill pill-ok" style="height: 18px; font-size: 10.5px;"><span class="pill-dot"></span>{{ __('Verified') }}</span>
                                @else
                                    <span class="pill pill-warn" style="height: 18px; font-size: 10.5px;"><span class="pill-dot"></span>{{ __('Pending review') }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                <div style="margin-top: 14px; padding: 10px 12px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 11.5px; color: var(--ink-3);">
                    {{ __('Tourism Tax: RM 10/night automatically applied to foreign guests at registered accommodations.') }}
                </div>
            </div>

            {{-- Workspace defaults --}}
            <div class="hauz-card" style="padding: 22px;">
                <div class="kicker" style="margin-bottom: 14px;">{{ __('Workspace defaults') }}</div>
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Default locale') }}</label>
                        <select class="input" name="default_locale">
                            <option value="ms" {{ old('default_locale', $tenant->default_locale) === 'ms' ? 'selected' : '' }}>Bahasa Malaysia (BM)</option>
                            <option value="en" {{ old('default_locale', $tenant->default_locale) === 'en' ? 'selected' : '' }}>English (EN)</option>
                        </select>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Plan') }}</label>
                        <div style="padding-top: 8px;">
                            @if (($tenant->subscription?->plan ?? 'free') === 'free')
                                <span class="pill"><span class="pill-dot"></span>{{ __('Free') }}</span>
                                <a href="{{ route('tenant.subscription') }}" style="font-size: 12px; color: var(--primary); margin-left: 8px;">{{ __('Upgrade →') }}</a>
                            @else
                                <span class="pill pill-pro"><span class="pill-dot"></span>{{ __('Pro') }} · RM49/{{ __('mo') }}</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('KYC status') }}</label>
                        <div style="padding-top: 8px;">
                            @php $cls = match($tenant->kyc_status){ 'verified' => 'pill-ok', 'rejected' => 'pill-err', default => 'pill-warn' }; @endphp
                            <span class="pill {{ $cls }}"><span class="pill-dot"></span>{{ ucfirst($tenant->kyc_status) }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="kicker" style="font-size: 9.5px; display:block; margin-bottom: 4px;">{{ __('Status') }}</label>
                        <div style="padding-top: 8px;">
                            @php $cls = $tenant->status === 'active' ? 'pill-ok' : 'pill-warn'; @endphp
                            <span class="pill {{ $cls }}"><span class="pill-dot"></span>{{ ucfirst($tenant->status) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ─── Brand & theme ──────────────────────────────────────── --}}
            @php
                $defaults = \App\Models\Tenant::THEME_DEFAULTS;
                $brand = [
                    'primary'   => old('primary_color',   $tenant->primary_color   ?? $defaults['primary']),
                    'secondary' => old('secondary_color', $tenant->secondary_color ?? $defaults['secondary']),
                    'accent'    => old('accent_color',    $tenant->accent_color    ?? $defaults['accent']),
                ];
                $presets = [
                    ['key' => 'sunset',    'label' => __('Sunset Orange'),       'primary' => '#d97757', 'secondary' => '#a8401e', 'accent' => '#d4a437'],
                    ['key' => 'coastal',   'label' => __('Coastal Blue'),        'primary' => '#2e7da6', 'secondary' => '#1e4d6b', 'accent' => '#5db4d6'],
                    ['key' => 'highland',  'label' => __('Highland Green'),      'primary' => '#4a7a4a', 'secondary' => '#2d5230', 'accent' => '#88a86b'],
                    ['key' => 'heritage',  'label' => __('Heritage Burgundy'),   'primary' => '#8b3a3a', 'secondary' => '#5a1f1f', 'accent' => '#c47e6e'],
                    ['key' => 'charcoal',  'label' => __('Modern Charcoal'),     'primary' => '#2d2d2d', 'secondary' => '#1a1a1a', 'accent' => '#d97757'],
                    ['key' => 'teal',      'label' => __('Tropical Teal'),       'primary' => '#1e8a8a', 'secondary' => '#0e5c5c', 'accent' => '#d4a437'],
                ];
            @endphp

            <div class="hauz-card" style="padding: 22px;"
                 x-data="{
                    primary:   '{{ $brand['primary'] }}',
                    secondary: '{{ $brand['secondary'] }}',
                    accent:    '{{ $brand['accent'] }}',
                    showPreview: false,
                    apply(p) { this.primary = p.primary; this.secondary = p.secondary; this.accent = p.accent; },
                    reset() { this.apply({{ json_encode($defaults) }}); },
                    isValid(v) { return /^#[0-9a-fA-F]{6}$/.test(v); },
                    contrastInk(hex) {
                        if (!this.isValid(hex)) return '#fff';
                        const h = hex.replace('#','');
                        const r = parseInt(h.substr(0,2),16), g = parseInt(h.substr(2,2),16), b = parseInt(h.substr(4,2),16);
                        return ((r*299 + g*587 + b*114) / 1000) >= 165 ? '#1a1614' : '#ffffff';
                    },
                 }">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom: 16px;">
                    <div>
                        <div class="kicker">{{ __('Brand & theme') }}</div>
                        <div style="margin-top: 4px; font-size: 13px; color: var(--ink-2); max-width: 520px;">
                            {{ __('Pick a brand palette. These colors flow through your dashboard chrome and the public booking page guests see at') }}
                            <span class="mono" style="color: var(--ink); background: var(--bg-sunk); padding: 2px 6px; border-radius: 4px; font-size: 11.5px;">{{ str_replace(['https://','http://'], '', $tenant->publicUrl()) }}</span>.
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost" @click="reset()" style="color: var(--ink-3); flex-shrink: 0;">
                        {{ __('Reset to default') }}
                    </button>
                </div>

                {{-- Preset palettes --}}
                <div class="kicker" style="font-size: 9.5px; margin-bottom: 8px;">{{ __('Quick presets') }}</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 10px; margin-bottom: 22px;">
                    @foreach ($presets as $p)
                        <button type="button"
                                @click="apply({{ json_encode($p) }})"
                                :class="primary === '{{ $p['primary'] }}' && secondary === '{{ $p['secondary'] }}' && accent === '{{ $p['accent'] }}' ? 'is-active' : ''"
                                style="text-align:left; padding: 12px; border-radius: var(--r-md); border: 1.5px solid var(--line); background: var(--bg-elev); cursor:pointer; transition: all 120ms; display:flex; flex-direction:column; gap: 10px;"
                                onmouseover="this.style.borderColor='var(--ink-4)'" onmouseout="this.style.borderColor=primary==='{{ $p['primary'] }}'&&secondary==='{{ $p['secondary'] }}'&&accent==='{{ $p['accent'] }}'?'var(--primary)':'var(--line)'"
                                x-bind:style="primary === '{{ $p['primary'] }}' && secondary === '{{ $p['secondary'] }}' && accent === '{{ $p['accent'] }}' ? 'border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent);' : ''">
                            <div style="display:flex; gap: 4px; align-items:center;">
                                <span style="width: 28px; height: 28px; border-radius: 6px; background: {{ $p['primary'] }}; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);"></span>
                                <span style="width: 22px; height: 22px; border-radius: 5px; background: {{ $p['secondary'] }}; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);"></span>
                                <span style="width: 18px; height: 18px; border-radius: 4px; background: {{ $p['accent'] }}; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);"></span>
                            </div>
                            <div style="font-size: 12px; font-weight: 600; color: var(--ink); letter-spacing: -0.005em;">{{ $p['label'] }}</div>
                        </button>
                    @endforeach
                </div>

                {{-- Custom color inputs --}}
                <div class="kicker" style="font-size: 9.5px; margin-bottom: 8px;">{{ __('Custom colors') }}</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 22px;">
                    {{-- Primary --}}
                    <div>
                        <label style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                            {{ __('Primary') }}
                            <span style="font-size: 10.5px; font-weight: 500; color: var(--ink-3);">· {{ __('CTAs, links, highlights') }}</span>
                        </label>
                        <div style="display:flex; gap: 8px; align-items:center;">
                            <label style="position:relative; width: 44px; height: 36px; border-radius: var(--r-md); border: 1px solid var(--line-2); cursor:pointer; overflow:hidden; flex-shrink: 0;"
                                   :style="`background: ${primary}`">
                                <input type="color" x-model="primary" style="position:absolute; inset: -4px; width: calc(100% + 8px); height: calc(100% + 8px); border: none; cursor:pointer; opacity: 0;">
                            </label>
                            <input class="input mono" type="text" name="primary_color" x-model="primary" value="{{ $brand['primary'] }}" maxlength="7" placeholder="#d97757" style="text-transform: lowercase;">
                        </div>
                    </div>

                    {{-- Secondary --}}
                    <div>
                        <label style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                            {{ __('Secondary') }}
                            <span style="font-size: 10.5px; font-weight: 500; color: var(--ink-3);">· {{ __('Chips, accents, deep tones') }}</span>
                        </label>
                        <div style="display:flex; gap: 8px; align-items:center;">
                            <label style="position:relative; width: 44px; height: 36px; border-radius: var(--r-md); border: 1px solid var(--line-2); cursor:pointer; overflow:hidden; flex-shrink: 0;"
                                   :style="`background: ${secondary}`">
                                <input type="color" x-model="secondary" style="position:absolute; inset: -4px; width: calc(100% + 8px); height: calc(100% + 8px); border: none; cursor:pointer; opacity: 0;">
                            </label>
                            <input class="input mono" type="text" name="secondary_color" x-model="secondary" value="{{ $brand['secondary'] }}" maxlength="7" placeholder="#a8401e" style="text-transform: lowercase;">
                        </div>
                    </div>

                    {{-- Accent --}}
                    <div>
                        <label style="display:flex; align-items:center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                            {{ __('Accent') }}
                            <span style="font-size: 10.5px; font-weight: 500; color: var(--ink-3);">· {{ __('Badges, pricing emphasis') }}</span>
                        </label>
                        <div style="display:flex; gap: 8px; align-items:center;">
                            <label style="position:relative; width: 44px; height: 36px; border-radius: var(--r-md); border: 1px solid var(--line-2); cursor:pointer; overflow:hidden; flex-shrink: 0;"
                                   :style="`background: ${accent}`">
                                <input type="color" x-model="accent" style="position:absolute; inset: -4px; width: calc(100% + 8px); height: calc(100% + 8px); border: none; cursor:pointer; opacity: 0;">
                            </label>
                            <input class="input mono" type="text" name="accent_color" x-model="accent" value="{{ $brand['accent'] }}" maxlength="7" placeholder="#d4a437" style="text-transform: lowercase;">
                        </div>
                    </div>
                </div>

                {{-- Live preview (collapsed by default) --}}
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 8px;">
                    <div class="kicker" style="font-size: 9.5px;">{{ __('Live preview') }}</div>
                    <button type="button" class="btn btn-sm btn-ghost" @click="showPreview = !showPreview" style="color: var(--ink-3); font-size: 11.5px;">
                        <span x-text="showPreview ? '{{ __('Hide preview') }}' : '{{ __('Show preview') }}'">{{ __('Show preview') }}</span>
                    </button>
                </div>
                <div x-show="showPreview" x-cloak style="border-radius: var(--r-lg); border: 1px solid var(--line-2); overflow:hidden; background: var(--bg-sunk);">
                    {{-- Mini hero --}}
                    <div :style="`background: radial-gradient(ellipse at 20% 30%, color-mix(in srgb, ${primary} 65%, transparent) 0%, transparent 55%), radial-gradient(ellipse at 80% 70%, color-mix(in srgb, ${secondary} 50%, transparent) 0%, transparent 55%), linear-gradient(135deg, ${secondary} 0%, ${primary} 45%, color-mix(in srgb, ${accent} 50%, ${primary}) 100%); padding: 24px 22px; color: #fff; text-shadow: 0 1px 8px rgba(0,0,0,.25);`">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px;">
                            <div>
                                <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; opacity: .9;">{{ __('Direct booking · No commission') }}</div>
                                <div style="font-size: 22px; font-weight: 700; letter-spacing: -0.015em; margin-top: 6px;">{{ $tenant->business_name }}</div>
                            </div>
                            <span :style="`background: ${accent}; color: ${contrastInk(accent)}; padding: 5px 11px; border-radius: 999px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; text-shadow: none; box-shadow: 0 4px 12px rgba(0,0,0,.15);`">{{ __('Featured') }}</span>
                        </div>
                    </div>

                    {{-- Body preview --}}
                    <div style="padding: 18px 22px; background: var(--bg-elev); display:flex; flex-direction:column; gap: 16px;">
                        {{-- Buttons --}}
                        <div style="display:flex; flex-wrap:wrap; gap: 10px; align-items:center;">
                            <button type="button" :style="`background: ${primary}; color: ${contrastInk(primary)}; border: none; padding: 0 16px; height: 40px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor:default; box-shadow: 0 4px 12px color-mix(in srgb, ${primary} 30%, transparent);`">
                                {{ __('Reserve now') }} →
                            </button>
                            <button type="button" :style="`background: transparent; color: ${secondary}; border: 1.5px solid ${secondary}; padding: 0 14px; height: 40px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor:default;`">
                                {{ __('Contact host') }}
                            </button>
                            <span :style="`background: color-mix(in srgb, ${primary} 12%, #fff); color: ${primary}; padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 600;`">● {{ __('Available') }}</span>
                            <span :style="`background: color-mix(in srgb, ${accent} 14%, #fff); color: color-mix(in srgb, ${accent} 70%, #000); padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 600;`">{{ __('Best value') }}</span>
                        </div>

                        {{-- Mini calendar preview --}}
                        <div style="display:flex; gap: 6px; align-items:center;">
                            @foreach (range(1, 7) as $d)
                                @php $state = $d === 3 ? 'in' : ($d === 5 ? 'out' : ($d >= 3 && $d <= 5 ? 'range' : 'avail')); @endphp
                                @if ($state === 'in' || $state === 'out')
                                    <div :style="`width: 38px; height: 44px; border-radius: 9px; background: linear-gradient(180deg, ${primary} 0%, color-mix(in srgb, ${primary} 80%, #000) 100%); color: ${contrastInk(primary)}; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size: 13px; font-weight: 700; box-shadow: 0 4px 10px color-mix(in srgb, ${primary} 30%, transparent);`">
                                        {{ 13 + $d }}
                                    </div>
                                @elseif ($state === 'range')
                                    <div :style="`width: 38px; height: 44px; border-radius: 9px; background: color-mix(in srgb, ${primary} 10%, #fff); color: color-mix(in srgb, ${primary} 75%, #000); display:flex; align-items:center; justify-content:center; font-size: 13px; font-weight: 600;`">
                                        {{ 13 + $d }}
                                    </div>
                                @else
                                    <div style="width: 38px; height: 44px; border-radius: 9px; background: var(--bg-elev); border: 1px solid var(--line); color: var(--ink-2); display:flex; align-items:center; justify-content:center; font-size: 13px; font-weight: 500;">
                                        {{ 13 + $d }}
                                    </div>
                                @endif
                            @endforeach
                            <div style="margin-left: 8px; font-size: 11px; color: var(--ink-3);">
                                <div>{{ __('2 nights selected') }}</div>
                                <div class="mono" :style="`color: color-mix(in srgb, ${secondary} 80%, #000); font-weight: 700; font-size: 13px;`">RM 440</div>
                            </div>
                        </div>

                        <template x-if="!isValid(primary) || !isValid(secondary) || !isValid(accent)">
                            <div style="padding: 8px 12px; background: var(--err-tint); color: var(--err); border-radius: var(--r-md); font-size: 11.5px;">
                                {{ __('One or more colors are invalid. Use 6-digit hex (e.g. #d97757).') }}
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content: flex-end; gap: 8px;">
                <button type="reset" class="btn">{{ __('Discard') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
