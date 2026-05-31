<x-app-layout :title="$property->name" :breadcrumbs="[__('Properties'), $property->city ?? __('Listing')]">
    @php
        $hue = crc32((string) $property->id) % 360;
        $hue2 = ($hue + 30) % 360;
        $heroGradient = "linear-gradient(135deg, oklch(72% 0.10 {$hue}) 0%, oklch(58% 0.12 {$hue2}) 60%, oklch(72% 0.08 ".(($hue + 60) % 360).") 100%)";

        $tabs = [
            ['key' => 'rooms',      'label' => __('Rooms'),      'icon' => 'bed'],
            ['key' => 'pricing',    'label' => __('Pricing'),    'icon' => 'card'],
            ['key' => 'facilities', 'label' => __('Facilities'), 'icon' => 'sparkle'],
            ['key' => 'policies',   'label' => __('Policies'),   'icon' => 'receipt'],
            ['key' => 'photos',     'label' => __('Photos'),     'icon' => 'building'],
        ];

        $bm = app()->getLocale() === 'ms';
    @endphp

    <div style="display:flex; flex-direction:column; gap:16px;">

        {{-- Back link --}}
        <a href="{{ route('tenant.properties.index') }}"
           style="align-self:flex-start; border:0; background:transparent;
                  display:inline-flex; align-items:center; gap:6px;
                  color: var(--ink-3); font-size:12px; text-decoration:none;">
            <x-icon name="arrow-left" :size="12"/> {{ __('All properties') }}
        </a>

        {{-- ───────────────────────────── HERO ─────────────────────────────
             Uses the real cover photo (is_hero) if uploaded, otherwise the
             first photo, otherwise a generated gradient with a friendly
             "add a cover photo" CTA. Photo count chip + Live/Draft status
             pill live in the hero. Stats strip below sits on a cleaner
             white panel inside the same card. --}}
        @php
            $cover = $property->photos->firstWhere('is_hero', true) ?? $property->photos->first();
            $photoCount = $property->photos->count();
            $isActive   = $property->status === 'active';
            $statusLabel= match ($property->status) {
                'active'   => __('Live'),
                'archived' => __('Archived'),
                default    => __('Draft'),
            };
            $tenantUrl  = $property->tenant?->publicUrl();
        @endphp

        <div class="card" style="padding:0; overflow:hidden; border: 1px solid var(--line);">
            {{-- ===== Photo / gradient cover ===== --}}
            <div style="position:relative; height: 320px;
                        @if ($cover)
                            background: #1a1f28 url('{{ $cover->url() }}') center/cover no-repeat;
                        @else
                            background: {{ $heroGradient }};
                        @endif">

                {{-- Readable dark scrim — denser at the bottom where text sits --}}
                <div style="position:absolute; inset:0;
                            background:
                                linear-gradient(180deg, rgba(0,0,0,0.18) 0%, transparent 30%, rgba(0,0,0,0.70) 100%),
                                linear-gradient(90deg, rgba(0,0,0,0.35) 0%, transparent 35%);"></div>

                {{-- Top-right chips: status + photo count --}}
                <div style="position:absolute; top:16px; right:16px; display:flex; gap:8px;">
                    <span style="display:inline-flex; align-items:center; gap:6px;
                                 padding: 5px 11px;
                                 background: rgba(255,255,255,0.95);
                                 color: var(--ink);
                                 border-radius: var(--r-pill);
                                 font-size: 11px; font-weight: 700;
                                 letter-spacing: .04em;
                                 box-shadow: 0 2px 8px rgba(0,0,0,0.18);">
                        <span style="width:7px; height:7px; border-radius:50%;
                                     background: {{ $isActive ? '#3f8b6a' : 'var(--ink-3)' }};
                                     {{ $isActive ? 'box-shadow: 0 0 0 3px rgba(63,139,106,0.25);' : '' }}"></span>
                        {{ $statusLabel }}
                    </span>

                    <a href="{{ route('tenant.properties.show', ['id' => $property->id, 'tab' => 'photos']) }}"
                       style="display:inline-flex; align-items:center; gap:6px;
                              padding: 5px 11px;
                              background: rgba(255,255,255,0.95);
                              color: var(--ink);
                              border-radius: var(--r-pill);
                              font-size: 11px; font-weight: 700;
                              text-decoration: none;
                              box-shadow: 0 2px 8px rgba(0,0,0,0.18);">
                        📷 {{ trans_choice('{0} Add photos|{1} 1 photo|[2,*] :count photos', $photoCount, ['count' => $photoCount]) }}
                    </a>
                </div>

                {{-- Bottom-left: location + name --}}
                <div style="position:absolute; left:24px; right:24px; bottom:24px; color:white;">
                    <div style="display:inline-flex; align-items:center; gap:6px;
                                padding: 4px 11px;
                                background: rgba(255,255,255,0.18);
                                backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
                                border-radius: var(--r-pill);
                                font-size: 11px; font-weight: 600;
                                letter-spacing: .03em;
                                margin-bottom: 12px;">
                        <x-icon name="pin" :size="11"/>
                        {{ $property->city ?: __('—') }}{{ $property->state ? ', '.$property->state : '' }}
                    </div>
                    <h1 style="margin:0; font-family: var(--font-display); font-size: 36px; line-height: 1.05;
                               font-weight: 700; color: white; letter-spacing: -0.01em;
                               text-shadow: 0 2px 16px rgba(0,0,0,0.4);">
                        {{ $property->name }}
                    </h1>
                    <div style="margin-top: 10px; display:flex; gap: 16px; flex-wrap: wrap; font-size: 13px; color: rgba(255,255,255,0.92);">
                        <span>🛏️ {{ trans_choice('{0} no rooms|{1} 1 room|[2,*] :count rooms', $property->rooms->count(), ['count' => $property->rooms->count()]) }}</span>
                        @if (($property->bathrooms ?? 0) > 0)
                            <span>🚿 {{ trans_choice('{1} 1 bathroom|[2,*] :count bathrooms', $property->bathrooms, ['count' => $property->bathrooms]) }}</span>
                        @endif
                        @if (($property->toilets ?? 0) > 0)
                            <span>🚽 {{ trans_choice('{1} 1 toilet|[2,*] :count toilets', $property->toilets, ['count' => $property->toilets]) }}</span>
                        @endif
                    </div>
                </div>

                {{-- "Add cover photo" CTA when there's no photo at all --}}
                @if (! $cover)
                    <a href="{{ route('tenant.properties.show', ['id' => $property->id, 'tab' => 'photos']) }}"
                       style="position:absolute; top:50%; left:50%; transform: translate(-50%, -50%);
                              padding: 10px 18px; border-radius: var(--r-pill);
                              background: rgba(255,255,255,0.95); color: var(--ink);
                              font-size: 13px; font-weight: 700; text-decoration: none;
                              display:inline-flex; align-items:center; gap:8px;
                              box-shadow: 0 6px 24px rgba(0,0,0,0.3);">
                        📷 {{ __('Add a cover photo') }}
                    </a>
                @endif
            </div>

            {{-- ===== Stats + actions strip ===== --}}
            <div style="padding: 14px 20px; display:flex; gap: 28px; align-items:center; flex-wrap: wrap; border-top: 1px solid var(--line);">
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('From / night') }}</div>
                    <div class="mono" style="font-size:15px; font-weight:700;">RM {{ number_format($startingRate, 0) }}</div>
                </div>
                <div style="width:1px; height:32px; background: var(--line);"></div>
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Occupancy · 30d') }}</div>
                    <div class="mono" style="font-size:15px; font-weight:700;">{{ $occupancy }}%</div>
                </div>
                <div style="width:1px; height:32px; background: var(--line);"></div>
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Rating') }}</div>
                    <div class="mono" style="font-size:15px; font-weight:700;">{{ $rating }} ★</div>
                </div>

                <div style="flex:1;"></div>

                @if($tenantUrl)
                    <a href="{{ $tenantUrl }}" target="_blank" rel="noopener" class="btn btn-sm" title="{{ $tenantUrl }}">
                        <x-icon name="link" :size="12"/> {{ __('Public booking link') }}
                    </a>
                @endif
                <a href="{{ route('tenant.calendar', ['property_id' => $property->id]) }}" class="btn btn-sm">
                    <x-icon name="calendar" :size="12"/> {{ __('Calendar') }}
                </a>
                <a href="{{ route('tenant.properties.edit', ['property' => $property->public_id]) }}" class="btn btn-sm btn-primary">
                    <x-icon name="cog" :size="12"/> {{ __('Edit property') }}
                </a>
            </div>
        </div>

        {{-- Tabs --}}
        <div style="display:flex; gap:4px; padding:3px; background: var(--bg-sunk); border-radius: var(--r-md); border: .5px solid var(--line); align-self:flex-start;">
            @foreach ($tabs as $t)
                @php $active = $tab === $t['key']; @endphp
                <a href="{{ route('tenant.properties.show', ['id' => $property->id, 'tab' => $t['key']]) }}"
                   class="btn btn-sm"
                   style="border:0; text-decoration:none;
                          background: {{ $active ? 'var(--bg-elev)' : 'transparent' }};
                          color: {{ $active ? 'var(--ink)' : 'var(--ink-3)' }};
                          box-shadow: {{ $active ? 'var(--sh-1)' : 'none' }};
                          font-weight: {{ $active ? '600' : '500' }};">
                    <x-icon :name="$t['icon']" :size="13"/> {{ $t['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Tab content --}}
        @if ($tab === 'rooms')
            @if ($property->rooms->isEmpty())
                <div class="card" style="padding:32px; text-align:center;">
                    <div class="display-3" style="margin-bottom:6px;">{{ __('No rooms yet') }}</div>
                    <p style="margin:0 0 14px; color: var(--ink-3); font-size:13px;">{{ __('Add your first room to start receiving bookings for this property.') }}</p>
                    <a href="{{ route('tenant.properties.edit', ['property' => $property->public_id]) }}" class="btn btn-primary btn-sm">{{ __('Add room') }}</a>
                </div>
            @else
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:12px;">
                    @foreach ($property->rooms as $r)
                        <div class="card" style="padding:16px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                                <div>
                                    <div style="font-size:14px; font-weight:600; margin-bottom:2px;">{{ $r->name }}</div>
                                    <div style="font-size:11.5px; color: var(--ink-3); display:inline-flex; align-items:center; gap:5px;">
                                        <x-icon name="bed" :size="11"/>
                                        {{ $r->beds }} {{ trans_choice('{1} bed|[2,*] beds', (int) $r->beds) }} · {{ __('sleeps :n', ['n' => $r->max_adults]) }}
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div class="mono" style="font-size:16px; font-weight:600;">RM {{ number_format((float) $r->base_price, 0) }}</div>
                                    <div style="font-size:10.5px; color: var(--ink-3);">{{ __('per night') }}</div>
                                </div>
                            </div>
                            <div style="display:flex; gap:6px; padding-top:10px; border-top:.5px solid var(--line); margin-top:4px;">
                                <a href="{{ route('tenant.properties.edit', ['property' => $property->public_id]) }}" class="btn btn-sm btn-ghost" style="font-size:11.5px;">{{ __('Edit rates') }}</a>
                                <a href="{{ route('tenant.calendar', ['property_id' => $property->id]) }}" class="btn btn-sm btn-ghost" style="font-size:11.5px;">{{ __('Block dates') }}</a>
                                <button type="button" class="btn btn-sm btn-ghost" style="font-size:11.5px;">{{ __('Photos') }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        @elseif ($tab === 'pricing')
            @php
                $isWholeHouse = $property->isWholeHousePricing();
                $wholeHouseRoom = $isWholeHouse ? $property->rooms->first() : null;
                $wholeHouseRoomId = $wholeHouseRoom?->id;

                $weekdays = [
                    1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'),
                    5 => __('Fri'), 6 => __('Sat'), 0 => __('Sun'),
                ];
                $ruleTypes = [
                    'weekend'        => __('Weekend (Fri-Sun by default)'),
                    'holiday'        => __('Public holiday / festive date'),
                    'school_holiday' => __('School holiday (Cuti Sekolah)'),
                    'season'         => __('Seasonal date range'),
                    'custom'         => __('Custom (pick weekdays + dates)'),
                ];
                $adjTypes = [
                    'percent'  => __('+/- % of base'),
                    'flat'     => __('+/- flat RM'),
                    'override' => __('Override to flat RM'),
                ];
            @endphp

            {{-- Base rates summary --}}
            <div class="card" style="padding:20px;">
                <div class="cm-eyebrow" style="margin-bottom:6px;">{{ __('Pricing engine') }}</div>
                <h3 style="margin:0 0 16px; font-size:16px; font-weight:700;">{{ __('Base rates per room') }}</h3>

                @if ($property->rooms->isEmpty())
                    <div style="font-size:13px; color: var(--ink-3);">{{ __('No rooms to price yet.') }}</div>
                @else
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        @foreach ($property->rooms as $r)
                            <div style="display:flex; align-items:center; gap:12px; padding:12px 14px;
                                        background: var(--bg-sunk); border:.5px solid var(--line);
                                        border-radius: var(--r-md);">
                                <div style="flex:1;">
                                    <div style="font-size:13px; font-weight:600;">{{ $r->name }}</div>
                                    <div style="font-size:11.5px; color: var(--ink-3);">{{ ucfirst($r->room_type ?? 'standard') }} · {{ $r->beds }} {{ __('beds') }}</div>
                                </div>
                                <div class="mono" style="font-size:15px; font-weight:700;">RM {{ number_format((float) $r->base_price, 0) }}<span style="font-size:11px; color: var(--ink-3); font-weight:500;"> / {{ __('night') }}</span></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Dynamic pricing rules --}}
            @php
                $rulesData = $property->pricingRules->mapWithKeys(fn ($r) => [
                    $r->id => [
                        'name'             => (string) $r->name,
                        'room_id'          => $r->room_id ? (string) $r->room_id : '',
                        'rule_type'        => $r->rule_type,
                        'priority'         => (int) $r->priority,
                        'weekday_mask'     => array_map('intval', $r->weekday_mask ?? []),
                        'date_from'        => optional($r->date_from)->toDateString() ?: '',
                        'date_to'          => optional($r->date_to)->toDateString() ?: '',
                        'adjustment_type'  => $r->adjustment_type,
                        'adjustment_value' => (float) $r->adjustment_value,
                        'active'           => (bool) $r->active,
                    ],
                ])->toArray();

                $rulesDataJson = json_encode(
                    empty($rulesData) ? new \stdClass() : $rulesData,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                );
                $defaultRoomId = $wholeHouseRoomId ?? '';
            @endphp

            <div class="card" style="padding:20px; margin-top: 16px;"
                 x-data="{
                    showForm: false,
                    editingId: null,
                    form: {},
                    rulesData: {{ $rulesDataJson }},
                    edit(id) {
                        const data = this.rulesData[id];
                        if (!data) { console.warn('pricing rule not found in client data:', id); return; }
                        this.editingId = id;
                        this.form = { ...data };
                        this.showForm = true;
                        this.$nextTick(() => this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' }));
                    },
                    addNew() {
                        this.editingId = null;
                        this.form = { active: true, rule_type: 'weekend', adjustment_type: 'percent', priority: 100, weekday_mask: [5,6,0], room_id: '{{ $defaultRoomId }}' };
                        this.showForm = !this.showForm;
                    }
                 }">

                @if (session('status'))
                    <div style="margin-bottom:14px; padding: 10px 14px; background: var(--ok-tint); color: var(--ok); border-radius: var(--r-md); font-size: 12.5px;">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div style="margin-bottom:14px; padding: 10px 14px; background: var(--err-tint); color: var(--err); border-radius: var(--r-md); font-size: 12.5px;">
                        @foreach ($errors->all() as $msg)<div>• {{ $msg }}</div>@endforeach
                    </div>
                @endif

                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:14px;">
                    <div>
                        <div style="font-size:13px; font-weight:600;">{{ __('Dynamic pricing rules') }}</div>
                        <div style="font-size:11.5px; color: var(--ink-3); margin-top:2px;">
                            {{ __('Auto-adjust nightly rates by weekday, date range, or both. Higher priority numbers apply later.') }}
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm"
                            @click="addNew()"
                            style="display:inline-flex; align-items:center; gap:6px;">
                        <x-icon name="plus" :size="13"/> {{ __('Add rule') }}
                    </button>
                </div>

                {{-- Inline create/edit form --}}
                <div x-show="showForm" x-cloak x-transition
                     style="padding: 18px; background: var(--bg-elev); border: 1px solid var(--line); border-radius: var(--r-md); margin-bottom: 16px;">
                    <form method="POST"
                          x-bind:action="editingId
                              ? `{{ route('tenant.properties.pricing.update', ['property' => $property->public_id, 'rule' => 'RULEID']) }}`.replace('RULEID', editingId)
                              : `{{ route('tenant.properties.pricing.store',  ['property' => $property->public_id]) }}`"
                          style="display:flex; flex-direction:column; gap:14px;">
                        @csrf
                        <template x-if="editingId"><input type="hidden" name="_method" value="PATCH"></template>

                        <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                            <div>
                                <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:4px;">{{ __('Rule name') }} *</label>
                                <input class="input" type="text" name="name" x-model="form.name" required maxlength="80" placeholder="{{ __('e.g. Weekend uplift, Hari Raya 2027') }}">
                            </div>
                            <div>
                                <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:4px;">{{ __('Applies to') }}</label>
                                @if ($isWholeHouse)
                                    {{-- Whole-house properties have one Room ("Whole house") — pre-locked, no choice needed --}}
                                    <input type="hidden" name="room_id" value="{{ $wholeHouseRoomId }}" x-model="form.room_id">
                                    <div class="input" style="display:inline-flex; align-items:center; gap:8px; background: var(--bg-tint); color: var(--ink-2); cursor:default; font-size: 13px;">
                                        🏠 {{ __('Whole house') }}
                                    </div>
                                @else
                                    <select class="input" name="room_id" x-model="form.room_id">
                                        <option value="">{{ __('All rooms') }}</option>
                                        @foreach ($property->rooms as $r)
                                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:4px;">{{ __('Rule type') }} *</label>
                                <select class="input" name="rule_type" x-model="form.rule_type">
                                    @foreach ($ruleTypes as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:4px;">{{ __('Priority') }}</label>
                                <input class="input" type="number" name="priority" x-model.number="form.priority" min="1" max="999" placeholder="100">
                                <div style="font-size:10.5px; color: var(--ink-3); margin-top:3px;">{{ __('Lower = applied first. Default 100.') }}</div>
                            </div>
                        </div>

                        {{-- Weekday checkboxes (visible for weekend & custom types) --}}
                        <div x-show="['weekend','custom'].includes(form.rule_type)">
                            <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:6px;">{{ __('Active weekdays') }}</label>
                            <div style="display:flex; gap: 6px; flex-wrap: wrap;">
                                @foreach ($weekdays as $idx => $label)
                                    <label style="cursor:pointer; display:inline-flex; align-items:center; gap:6px;
                                                  padding: 7px 12px; border-radius: var(--r-pill);
                                                  border: 1.5px solid var(--line); background: var(--bg);
                                                  font-size: 12px; font-weight: 600;"
                                           x-bind:style="(form.weekday_mask || []).includes({{ $idx }})
                                               ? 'border-color: var(--primary); background: var(--primary-tint); color: var(--primary-deep);'
                                               : ''">
                                        <input type="checkbox" name="weekday_mask[]" value="{{ $idx }}"
                                               x-model.number="form.weekday_mask"
                                               style="margin:0; accent-color: var(--primary);">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Date range — required for holiday / school_holiday / season, optional otherwise --}}
                        @php $datedTypes = "['holiday','school_holiday','season'].includes(form.rule_type)"; @endphp
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:4px;">
                                    {{ __('Date from') }} <span x-text="{{ $datedTypes }} ? '*' : ''" style="color: var(--err);"></span>
                                    <span x-show="!({{ $datedTypes }})" style="color: var(--ink-3); font-weight:500;">{{ __('(optional)') }}</span>
                                </label>
                                <input class="input" type="date" name="date_from" x-model="form.date_from" x-bind:required="{{ $datedTypes }}">
                            </div>
                            <div>
                                <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:4px;">
                                    {{ __('Date to') }} <span x-text="{{ $datedTypes }} ? '*' : ''" style="color: var(--err);"></span>
                                    <span x-show="!({{ $datedTypes }})" style="color: var(--ink-3); font-weight:500;">{{ __('(optional)') }}</span>
                                </label>
                                <input class="input" type="date" name="date_to" x-model="form.date_to" x-bind:required="{{ $datedTypes }}">
                            </div>
                        </div>

                        {{-- School holiday helper hint --}}
                        <div x-show="form.rule_type === 'school_holiday'" x-cloak
                             style="padding: 10px 12px; background: var(--info-tint); color: var(--info); border-radius: var(--r-md); font-size: 11.5px; line-height: 1.5;">
                            📚 <strong>{{ __('Tip:') }}</strong>
                            {{ __('Malaysian school terms differ for Group A (Johor, Kedah, Kelantan, Terengganu — Sun-Thu week) and Group B (rest of Malaysia — Mon-Fri week). Fill the date range that matches YOUR target guests\' state. See moe.gov.my for the latest calendar.') }}
                        </div>

                        {{-- Adjustment --}}
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:4px;">{{ __('Adjustment type') }} *</label>
                                <select class="input" name="adjustment_type" x-model="form.adjustment_type">
                                    @foreach ($adjTypes as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="kicker" style="font-size:9.5px; display:block; margin-bottom:4px;">
                                    <span x-text="form.adjustment_type === 'percent' ? '{{ __('% to add (use - for discount)') }}' : (form.adjustment_type === 'flat' ? '{{ __('RM to add (use - for discount)') }}' : '{{ __('Flat RM override') }}')"></span> *
                                </label>
                                <input class="input" type="number" name="adjustment_value" x-model.number="form.adjustment_value" step="0.01" required placeholder="20">
                            </div>
                        </div>

                        <label style="display:inline-flex; align-items:center; gap:8px; font-size:12.5px; color: var(--ink-2); cursor:pointer;">
                            <input type="checkbox" name="active" value="1" x-model="form.active" style="accent-color: var(--primary);">
                            {{ __('Rule is active') }}
                        </label>

                        <div style="display:flex; gap:8px; justify-content:flex-end; padding-top: 4px;">
                            <button type="button" class="btn btn-sm" @click="showForm = false">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary btn-sm" x-text="editingId ? '{{ __('Save changes') }}' : '{{ __('Add rule') }}'">{{ __('Add rule') }}</button>
                        </div>
                    </form>
                </div>

                {{-- Existing rules list --}}
                @if ($property->pricingRules->isEmpty())
                    <div style="padding: 28px; text-align:center; border: 1.5px dashed var(--line-2); border-radius: var(--r-md); color: var(--ink-3); font-size: 13px;">
                        {{ __('No pricing rules yet. Click "Add rule" to define a weekend uplift, holiday markup, or seasonal pricing.') }}
                    </div>
                @else
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        @foreach ($property->pricingRules as $rule)
                            @php
                                $adj = (float) $rule->adjustment_value;
                                $adjText = match ($rule->adjustment_type) {
                                    'percent'  => ($adj >= 0 ? '+' : '').number_format($adj, 1).'%',
                                    'flat'     => ($adj >= 0 ? '+RM ' : '-RM ').number_format(abs($adj), 0),
                                    'override' => '= RM '.number_format($adj, 0),
                                    default    => $adj,
                                };
                                $scope = $rule->room_id
                                    ? ($property->rooms->firstWhere('id', $rule->room_id)?->name ?? __('Specific room'))
                                    : __('All rooms');
                            @endphp
                            <div style="display:flex; align-items:center; gap:14px; padding: 12px 14px;
                                        background: {{ $rule->active ? 'var(--bg-elev)' : 'var(--bg-sunk)' }};
                                        border: 1px solid {{ $rule->active ? 'var(--line)' : 'var(--line-2)' }};
                                        border-radius: var(--r-md);
                                        opacity: {{ $rule->active ? '1' : '0.6' }};">
                                <div style="flex:1; min-width:0;">
                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom: 3px;">
                                        <span style="font-size:13px; font-weight:600;">{{ $rule->name }}</span>
                                        <span class="pill" style="font-size:9.5px; padding: 2px 7px; background: var(--bg-tint); color: var(--ink-3); text-transform:uppercase; letter-spacing:.06em;">{{ $rule->rule_type }}</span>
                                        @unless ($rule->active)
                                            <span class="pill" style="font-size:9.5px; padding: 2px 7px; background: var(--warn-tint); color: var(--warn);">{{ __('paused') }}</span>
                                        @endunless
                                    </div>
                                    <div style="font-size:11.5px; color: var(--ink-3);">
                                        {{ $scope }}
                                        @if ($rule->weekday_mask)
                                            · {{ collect($rule->weekday_mask)->map(fn($d) => $weekdays[$d] ?? '')->filter()->join(', ') }}
                                        @endif
                                        @if ($rule->date_from || $rule->date_to)
                                            · {{ optional($rule->date_from)->format('d M Y') ?? '…' }} → {{ optional($rule->date_to)->format('d M Y') ?? '…' }}
                                        @endif
                                    </div>
                                </div>
                                <div class="mono" style="font-size:14px; font-weight:700; color: {{ $adj >= 0 || $rule->adjustment_type === 'override' ? 'var(--primary-deep)' : 'var(--err)' }};">
                                    {{ $adjText }}
                                </div>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="btn btn-sm btn-ghost"
                                            @click="edit({{ $rule->id }})"
                                            style="font-size:11.5px;">{{ __('Edit') }}</button>
                                    <form method="POST" action="{{ route('tenant.properties.pricing.toggle', ['property' => $property->public_id, 'rule' => $rule->id]) }}?tab=pricing" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-ghost" style="font-size:11.5px;" title="{{ $rule->active ? __('Pause') : __('Resume') }}">
                                            {{ $rule->active ? '⏸' : '▶' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('tenant.properties.pricing.destroy', ['property' => $property->public_id, 'rule' => $rule->id]) }}?tab=pricing"
                                          onsubmit="return confirm('{{ addslashes(__('Delete pricing rule \':name\'?', ['name' => $rule->name])) }}');"
                                          style="margin:0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-ghost" style="font-size:11.5px; color: var(--err);">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div style="margin-top:14px; padding:10px 14px; background: var(--bg-sunk); border-radius: var(--r-md); font-size:11.5px; color: var(--ink-3); line-height:1.5;">
                    <strong style="color: var(--ink-2);">{{ __('How it works:') }}</strong>
                    {{ __('Rules are applied in priority order during booking quote. Example: base RM 220 + "Weekend +20%" rule = RM 264 on Fri/Sat/Sun nights. Inactive rules are ignored.') }}
                </div>
            </div>

        @elseif ($tab === 'facilities')
            <div class="card" style="padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; gap:12px;">
                    <div>
                        <div style="font-size:13px; font-weight:600;">{{ __('What this homestay offers') }}</div>
                        <div style="font-size:11.5px; color: var(--ink-3); margin-top:2px;">{{ __('Guests see these on your public booking page.') }}</div>
                    </div>
                    <a href="{{ route('tenant.properties.edit', ['property' => $property->public_id]) }}" class="btn btn-sm">{{ __('Edit facilities') }}</a>
                </div>

                {{-- Bathroom + toilet counts --}}
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; margin-bottom:18px;">
                    <div style="padding: 12px 14px; background: var(--bg-elev); border-radius: var(--r-md); border: 1px solid var(--line);">
                        <div style="font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color: var(--ink-3); margin-bottom:3px;">🚿 {{ __('Bathrooms') }}</div>
                        <div style="font-size:20px; font-weight:700; color: var(--ink); font-family: var(--font-mono);">{{ $property->bathrooms ?? 0 }}</div>
                    </div>
                    <div style="padding: 12px 14px; background: var(--bg-elev); border-radius: var(--r-md); border: 1px solid var(--line);">
                        <div style="font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color: var(--ink-3); margin-bottom:3px;">🚽 {{ __('Separate toilets') }}</div>
                        <div style="font-size:20px; font-weight:700; color: var(--ink); font-family: var(--font-mono);">{{ $property->toilets ?? 0 }}</div>
                    </div>
                </div>

                {{-- Amenities grouped by category --}}
                @php
                    $selectedIds = $property->amenities->pluck('id')->all();
                @endphp
                @if (empty($selectedIds))
                    <div style="padding: 20px; text-align:center; border: 1.5px dashed var(--line-2); border-radius: var(--r-md); color: var(--ink-3); font-size: 13px;">
                        {{ __('No facilities listed yet.') }}
                        <a href="{{ route('tenant.properties.edit', ['property' => $property->public_id]) }}" style="color: var(--primary); font-weight:600;">{{ __('Add facilities →') }}</a>
                    </div>
                @else
                    <div style="display:flex; flex-direction:column; gap:18px;">
                        @foreach ($amenityGroups as $catKey => $group)
                            @php $groupItems = $group['items']->filter(fn($a) => in_array($a->id, $selectedIds)); @endphp
                            @if ($groupItems->isNotEmpty())
                                <div>
                                    <div style="font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color: var(--ink-3); margin-bottom:8px;">
                                        {{ $group['label'] }}
                                    </div>
                                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:8px;">
                                        @foreach ($groupItems as $a)
                                            <div style="padding: 10px 12px;
                                                        border: 1.5px solid var(--primary);
                                                        background: var(--primary-tint);
                                                        color: var(--primary-deep);
                                                        border-radius: var(--r-md);
                                                        display:flex; align-items:center; gap:9px;
                                                        font-size:12.5px; font-weight:600;">
                                                <span style="font-size:16px; line-height:1;">{{ $a->icon }}</span>
                                                <span style="flex:1;">{{ $bm ? $a->label_bm : $a->label_en }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

        @elseif ($tab === 'policies')
            @if (session('status'))
                <div class="hauz-card" style="padding: 10px 14px; margin-bottom: 14px;
                            border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST"
                  action="{{ route('tenant.properties.policies.update', $property->public_id) }}"
                  class="card" style="padding:20px; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                @csrf
                @method('PATCH')

                <div>
                    <div style="font-size:12px; font-weight:600; margin-bottom:2px;">{{ __('Check-in') }}</div>
                    <div style="font-size:11.5px; color: var(--ink-3); margin-bottom:6px;">{{ __('Earliest time guests may arrive') }}</div>
                    <input class="input" type="time" name="check_in_time"
                           value="{{ old('check_in_time', \Illuminate\Support\Str::of((string) $property->check_in_time)->limit(5, '')) }}" required/>
                    @error('check_in_time')
                        <div style="font-size: 11.5px; color: var(--err); margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <div style="font-size:12px; font-weight:600; margin-bottom:2px;">{{ __('Check-out') }}</div>
                    <div style="font-size:11.5px; color: var(--ink-3); margin-bottom:6px;">{{ __('Latest time guests must leave') }}</div>
                    <input class="input" type="time" name="check_out_time"
                           value="{{ old('check_out_time', \Illuminate\Support\Str::of((string) $property->check_out_time)->limit(5, '')) }}" required/>
                    @error('check_out_time')
                        <div style="font-size: 11.5px; color: var(--err); margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>

                <div style="grid-column: 1 / -1;">
                    <div style="font-size:12px; font-weight:600; margin-bottom:2px;">{{ __('House rules') }}</div>
                    <div style="font-size:11.5px; color: var(--ink-3); margin-bottom:6px;">{{ __('Shown to guests on the booking page.') }}</div>
                    <textarea class="input" name="house_rules" rows="3" maxlength="1000"
                              style="height:auto; padding:10px; font-family: inherit;"
                              placeholder="{{ __('No smoking · Quiet hours 11pm–7am · Respect local customs · Halal-only kitchen') }}">{{ old('house_rules', is_string($property->house_rules) ? $property->house_rules : '') }}</textarea>
                    @error('house_rules')
                        <div style="font-size: 11.5px; color: var(--err); margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>

                <div style="grid-column: 1 / -1;">
                    <div style="font-size:12px; font-weight:600; margin-bottom:2px;">{{ __('Cancellation policy') }}</div>
                    <div style="font-size:11.5px; color: var(--ink-3); margin-bottom:6px;">{{ __('Refund rules when a guest cancels.') }}</div>
                    <textarea class="input" name="cancellation_policy" rows="3" maxlength="1000"
                              style="height:auto; padding:10px; font-family: inherit;"
                              placeholder="{{ __('Free cancellation up to 7 days before check-in. 50% refund within 7 days. Non-refundable within 48h.') }}">{{ old('cancellation_policy', $property->cancellation_policy === 'flexible' ? '' : $property->cancellation_policy) }}</textarea>
                    @error('cancellation_policy')
                        <div style="font-size: 11.5px; color: var(--err); margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>

                <div style="grid-column: 1 / -1; display:flex; justify-content: flex-end; gap: 8px;">
                    <button type="submit" class="btn btn-primary">{{ __('Save policies') }}</button>
                </div>
            </form>

            <div style="margin-top: 12px; padding: 12px 14px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 11.5px; color: var(--ink-3);">
                {{ __('Coming soon: minimum nights and deposit percentage per property.') }}
            </div>

        @elseif ($tab === 'photos')
            <style>
                @keyframes tl-spin { to { transform: rotate(360deg); } }
                .tl-spinner {
                    width: 44px; height: 44px;
                    border: 4px solid color-mix(in srgb, var(--primary) 22%, transparent);
                    border-top-color: var(--primary);
                    border-radius: 50%;
                    animation: tl-spin 0.7s linear infinite;
                }
                /* Static layout of each photo tile.
                   IMPORTANT: position:relative lives here, NOT in the
                   inline style — Alpine's x-bind:style on the same element
                   was clobbering it on the initial render, which made the
                   tile's absolute children (★ Cover badge, category select)
                   escape and anchor to the next positioned ancestor (the
                   outer .card), so they piled up in the card's top-left
                   and top-right corners over the Photos header. */
                .tl-photo-tile {
                    position: relative;
                    aspect-ratio: 4 / 3;
                    border-radius: var(--r-md);
                    overflow: hidden;
                    background: var(--bg-elev);
                    border: 1.5px solid var(--line);
                    transition: transform 160ms ease, box-shadow 160ms ease;
                }
                .tl-photo-tile.is-hero {
                    border-color: var(--primary);
                }
            </style>

            <div class="card"
                 style="padding:20px; position:relative;"
                 x-data="{
                    uploading: false,
                    fileCount: 0,
                    pick() { this.$refs.picker.click(); },
                    onPicked(e) {
                        this.fileCount = e.target.files?.length || 0;
                        if (this.fileCount > 0) {
                            this.uploading = true;
                            this.$refs.uploadForm.submit();
                        }
                    },
                 }">

                {{-- ==== Loading overlay: viewport-fixed so it's always centered in the user's view, regardless of where they've scrolled ==== --}}
                <div x-show="uploading" x-cloak x-transition.opacity
                     style="position:fixed; inset:0; z-index:9999;
                            background: rgba(15,25,40,0.55);
                            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
                            display:flex; align-items:center; justify-content:center;">
                    <div style="background: var(--bg);
                                border-radius: var(--r-xl);
                                padding: 32px 40px;
                                box-shadow: 0 24px 64px -12px rgba(0,0,0,0.4);
                                display:flex; flex-direction:column; align-items:center; gap: 16px;
                                max-width: 380px; width: calc(100% - 32px);">
                        <div class="tl-spinner"></div>
                        <div style="font-size:16px; font-weight:700; color: var(--ink); letter-spacing:-0.005em;">
                            <span x-text="`{{ __('Uploading') }} ${fileCount} {{ __('photo(s)…') }}`"></span>
                        </div>
                        <div style="font-size:12.5px; color: var(--ink-3); text-align:center; line-height:1.5;">
                            {{ __('Resizing and uploading to cloud storage. A few seconds per photo — please keep this tab open.') }}
                        </div>
                    </div>
                </div>

                @if (session('status'))
                    <div style="margin-bottom:14px; padding: 10px 14px; background: var(--ok-tint); color: var(--ok); border-radius: var(--r-md); font-size: 12.5px;">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div style="margin-bottom:14px; padding: 10px 14px; background: var(--err-tint); color: var(--err); border-radius: var(--r-md); font-size: 12.5px;">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div style="margin-bottom:14px; padding: 10px 14px; background: var(--err-tint); color: var(--err); border-radius: var(--r-md); font-size: 12.5px;">
                        @foreach ($errors->all() as $msg)<div>• {{ $msg }}</div>@endforeach
                    </div>
                @endif

                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:14px;">
                    <div>
                        <div style="font-size:13px; font-weight:600;">{{ __('Photos') }}</div>
                        <div style="font-size:11.5px; color: var(--ink-3); margin-top:2px;">
                            {{ trans_choice('{0} No photos yet — upload your first one|{1} 1 photo|[2,*] :count photos', $property->photos->count(), ['count' => $property->photos->count()]) }}
                            @if ($property->photos->isNotEmpty()) · {{ __('Hover a photo to set as cover or delete.') }} @endif
                        </div>
                    </div>

                    {{-- Upload form: hidden picker + visible "Upload photos" button --}}
                    <form method="POST"
                          action="{{ route('tenant.properties.photos.store', ['property' => $property->public_id]) }}?tab=photos"
                          enctype="multipart/form-data"
                          style="margin:0;"
                          x-ref="uploadForm">
                        @csrf
                        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
                               x-ref="picker" style="display:none;"
                               @change="onPicked($event)">
                        <button type="button" class="btn btn-primary btn-sm"
                                @click="pick()"
                                :disabled="uploading"
                                style="display:inline-flex; align-items:center; gap:6px;">
                            <x-icon name="plus" :size="13"/>
                            <span>{{ __('Upload photos') }}</span>
                        </button>
                    </form>
                </div>

                @if ($property->photos->isEmpty())
                    {{-- Empty state — click anywhere to open picker --}}
                    <button type="button"
                            @click="pick()"
                            :disabled="uploading"
                            style="width:100%; padding: 48px 24px; border: 2px dashed var(--line-2); background: var(--bg-elev); border-radius: var(--r-lg); cursor:pointer;
                                   display:flex; flex-direction:column; align-items:center; gap: 10px; color: var(--ink-3);">
                        <div style="font-size:36px; line-height:1;">📷</div>
                        <div style="font-size:14px; font-weight:600; color: var(--ink-2);">{{ __('Drop your first photo') }}</div>
                        <div style="font-size:11.5px;">{{ __('JPG, PNG or WebP — up to 8 MB each. Resized to 2400 px wide on save.') }}</div>
                    </button>
                @else
                    @php
                        $categories = \App\Models\PropertyPhoto::categories();
                        $bm = app()->getLocale() === 'ms';
                    @endphp
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">
                        @foreach ($property->photos as $photo)
                            @php
                                $cat = $photo->category && isset($categories[$photo->category]) ? $categories[$photo->category] : null;
                            @endphp
                            <div class="tl-photo-tile {{ $photo->is_hero ? 'is-hero' : '' }}"
                                 x-data="{ hover: false, confirmDel: false }"
                                 @mouseenter="hover = true"
                                 @mouseleave="hover = false; confirmDel = false"
                                 x-bind:style="hover ? 'transform: translateY(-2px); box-shadow: 0 8px 24px -6px rgba(15,25,40,0.18);' : ''">
                                <img src="{{ $photo->url() }}" alt=""
                                     style="width:100%; height:100%; object-fit:cover; display:block;"
                                     loading="lazy">

                                {{-- Hero badge (top-left) --}}
                                @if ($photo->is_hero)
                                    <div style="position:absolute; top:8px; left:8px; padding:3px 8px; background: var(--primary); color: var(--primary-ink); font-size:9.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.3);">
                                        ★ {{ __('Cover') }}
                                    </div>
                                @endif

                                {{-- Category select (top-right) — submit on change --}}
                                <form method="POST"
                                      action="{{ route('tenant.properties.photos.category', ['property' => $property->public_id, 'photo' => $photo->id]) }}?tab=photos"
                                      style="position:absolute; top:6px; right:6px; margin:0;">
                                    @csrf
                                    @method('PATCH')
                                    <select name="category"
                                            onchange="this.form.submit()"
                                            title="{{ __('Tag this photo') }}"
                                            style="appearance:none; -webkit-appearance:none;
                                                   padding: 3px 22px 3px 8px;
                                                   font-size: 10.5px; font-weight: 600;
                                                   border: 0; border-radius: 4px;
                                                   background: {{ $cat ? 'var(--primary)' : 'rgba(255,255,255,0.92)' }};
                                                   color: {{ $cat ? 'var(--primary-ink)' : 'var(--ink)' }};
                                                   box-shadow: 0 1px 3px rgba(0,0,0,.3);
                                                   cursor: pointer;
                                                   background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'10\' height=\'10\' viewBox=\'0 0 10 10\'><path d=\'M2 4l3 3 3-3\' stroke=\'{{ $cat ? 'white' : 'black' }}\' stroke-width=\'1.4\' fill=\'none\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/></svg>');
                                                   background-repeat: no-repeat;
                                                   background-position: right 6px center;">
                                        <option value="" {{ $photo->category ? '' : 'selected' }}>{{ __('🏷️ Tag photo') }}</option>
                                        @foreach ($categories as $key => $c)
                                            <option value="{{ $key }}" {{ $photo->category === $key ? 'selected' : '' }}>
                                                {{ $c['emoji'] }} {{ $bm ? $c['bm'] : $c['en'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>

                                {{-- ── Hover action bar (slides up from the bottom) ── --}}
                                <div x-show="hover && !confirmDel" x-transition.opacity.duration.150ms
                                     style="position:absolute; bottom:0; left:0; right:0; padding: 10px;
                                            background: linear-gradient(180deg, transparent 0%, rgba(15,25,40,0.78) 100%);
                                            display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                    @unless ($photo->is_hero)
                                        <form method="POST" action="{{ route('tenant.properties.photos.hero', ['property' => $property->public_id, 'photo' => $photo->id]) }}?tab=photos" style="margin:0;">
                                            @csrf
                                            <button type="submit" title="{{ __('Set as cover') }}"
                                                    style="display:inline-flex; align-items:center; gap:5px;
                                                           height: 30px; padding: 0 11px;
                                                           border: 0; border-radius: 999px;
                                                           background: rgba(255,255,255,0.95);
                                                           color: var(--ink);
                                                           font-size: 11.5px; font-weight: 600;
                                                           cursor:pointer;
                                                           box-shadow: 0 2px 6px rgba(0,0,0,0.25);
                                                           transition: background 120ms;"
                                                    onmouseover="this.style.background='white'"
                                                    onmouseout="this.style.background='rgba(255,255,255,0.95)'">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                                {{ __('Cover') }}
                                            </button>
                                        </form>
                                    @endunless

                                    {{-- Delete trigger — opens the inline confirm bar --}}
                                    <button type="button"
                                            @click="confirmDel = true"
                                            title="{{ __('Delete photo') }}"
                                            style="display:inline-flex; align-items:center; gap:5px;
                                                   height: 30px; padding: 0 11px;
                                                   border: 0; border-radius: 999px;
                                                   background: rgba(255,255,255,0.95);
                                                   color: #b91c1c;
                                                   font-size: 11.5px; font-weight: 600;
                                                   cursor:pointer;
                                                   box-shadow: 0 2px 6px rgba(0,0,0,0.25);
                                                   transition: background 120ms;"
                                            onmouseover="this.style.background='#fee2e2'"
                                            onmouseout="this.style.background='rgba(255,255,255,0.95)'">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        {{ __('Delete') }}
                                    </button>
                                </div>

                                {{-- ── Inline delete confirmation (overlays the bottom half on confirm) ── --}}
                                <div x-show="confirmDel" x-cloak x-transition
                                     style="position:absolute; left:0; right:0; bottom:0; padding: 12px;
                                            background: rgba(15,25,40,0.94);
                                            backdrop-filter: blur(4px);
                                            display:flex; flex-direction:column; gap: 10px;">
                                    <div style="font-size: 12.5px; font-weight: 600; color: white; text-align:center; line-height:1.35;">
                                        {{ __('Delete this photo?') }}
                                    </div>
                                    <div style="display:flex; gap: 6px;">
                                        <button type="button"
                                                @click="confirmDel = false"
                                                style="flex:1; height: 32px; border: 1px solid rgba(255,255,255,0.3);
                                                       border-radius: 6px; background: transparent; color: white;
                                                       font-size: 11.5px; font-weight: 600; cursor:pointer;">
                                            {{ __('Cancel') }}
                                        </button>
                                        <form method="POST" action="{{ route('tenant.properties.photos.destroy', ['property' => $property->public_id, 'photo' => $photo->id]) }}?tab=photos" style="margin:0; flex:1;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    style="width:100%; height: 32px; border: 0;
                                                           border-radius: 6px; background: #dc2626; color: white;
                                                           font-size: 11.5px; font-weight: 700; cursor:pointer;
                                                           display:inline-flex; align-items:center; justify-content:center; gap:5px;
                                                           transition: background 120ms;"
                                                    onmouseover="this.style.background='#b91c1c'"
                                                    onmouseout="this.style.background='#dc2626'">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- About strip --}}
        @if (!empty($property->description_en) || !empty($property->description_bm))
            <div class="card" style="padding:18px;">
                <div class="cm-eyebrow" style="margin-bottom:6px;">{{ __('About') }}</div>
                <p style="margin:0; font-size:14px; line-height:1.55; color: var(--ink-2);">
                    {{ $property->description_en ?? $property->description_bm }}
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
