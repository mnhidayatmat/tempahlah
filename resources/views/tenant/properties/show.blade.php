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

        {{-- Hero card --}}
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="position:relative; height:180px; background: {{ $heroGradient }};">
                <div style="position:absolute; inset:0;
                            background:
                              radial-gradient(circle at 20% 30%, rgba(255,255,255,0.18) 0%, transparent 40%),
                              radial-gradient(circle at 80% 70%, rgba(0,0,0,0.20) 0%, transparent 45%),
                              linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.35) 100%);"></div>
                <div style="position:absolute; left:20px; bottom:20px; color: white;">
                    <div class="cm-eyebrow" style="color: rgba(255,255,255,.9); margin-bottom:4px;">
                        <x-icon name="pin" :size="11" style="vertical-align:middle;"/> {{ $property->city ?? '—' }}{{ $property->state ? ', '.$property->state : '' }}
                    </div>
                    <div style="font-family: var(--font-display); font-size:32px; line-height:1.05; color:white;
                                text-shadow: 0 1px 8px rgba(0,0,0,.3); font-weight:600;">
                        {{ $property->name }}
                    </div>
                </div>
            </div>

            <div style="padding:14px 20px; display:flex; gap:24px; align-items:center; flex-wrap:wrap;">
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Rooms') }}</div>
                    <div class="mono" style="font-size:14px; font-weight:600;">{{ $property->rooms->count() }}</div>
                </div>
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('From / night') }}</div>
                    <div class="mono" style="font-size:14px; font-weight:600;">RM {{ number_format($startingRate, 0) }}</div>
                </div>
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Occupancy · 30d') }}</div>
                    <div class="mono" style="font-size:14px; font-weight:600;">{{ $occupancy }}%</div>
                </div>
                <div>
                    <div class="cm-eyebrow" style="margin-bottom:2px;">{{ __('Rating') }}</div>
                    <div class="mono" style="font-size:14px; font-weight:600;">{{ $rating }} ★</div>
                </div>
                <div style="flex:1;"></div>
                @php $tenantUrl = $property->tenant?->publicUrl(); @endphp
                @if($tenantUrl)
                    <a href="{{ $tenantUrl }}" target="_blank" rel="noopener" class="btn btn-sm" title="{{ $tenantUrl }}">
                        <x-icon name="link" :size="12"/> {{ __('Public booking link') }}
                    </a>
                @endif
                <a href="{{ route('tenant.calendar', ['property_id' => $property->id]) }}" class="btn btn-sm">
                    <x-icon name="calendar" :size="12"/> {{ __('Calendar') }}
                </a>
                <a href="{{ route('tenant.properties.edit', ['property' => $property->public_id]) }}" class="btn btn-sm btn-primary">
                    <x-icon name="plus" :size="12"/> {{ __('New room') }}
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

                <div style="margin-top:16px; padding:14px 16px; background: var(--pro-tint); border-radius: var(--r-md); border:.5px solid var(--line-2); display:flex; align-items:center; gap:12px;">
                    <x-icon name="sparkle" :size="16" style="color: var(--pro);"/>
                    <div style="flex:1;">
                        <div style="font-size:13px; font-weight:600;">{{ __('Dynamic pricing rules') }}</div>
                        <div style="font-size:11.5px; color: var(--ink-3);">{{ __('Weekend uplifts, holiday markups, last-minute deals — coming on Pro.') }}</div>
                    </div>
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
            <div class="card" style="padding:20px; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div>
                    <div style="font-size:12px; font-weight:600; margin-bottom:2px;">{{ __('Check-in') }}</div>
                    <div style="font-size:11.5px; color: var(--ink-3); margin-bottom:6px;">{{ __('Earliest time guests may arrive') }}</div>
                    <input class="input" type="time" value="{{ $property->check_in_time ?? '15:00' }}" disabled/>
                </div>
                <div>
                    <div style="font-size:12px; font-weight:600; margin-bottom:2px;">{{ __('Check-out') }}</div>
                    <div style="font-size:11.5px; color: var(--ink-3); margin-bottom:6px;">{{ __('Latest time guests must leave') }}</div>
                    <input class="input" type="time" value="{{ $property->check_out_time ?? '11:00' }}" disabled/>
                </div>
                <div>
                    <div style="font-size:12px; font-weight:600; margin-bottom:2px;">{{ __('Min. nights') }}</div>
                    <input class="input" value="{{ $property->min_nights ?? 1 }}" disabled/>
                </div>
                <div>
                    <div style="font-size:12px; font-weight:600; margin-bottom:2px;">{{ __('Deposit %') }}</div>
                    <div style="font-size:11.5px; color: var(--ink-3); margin-bottom:6px;">{{ __('Non-refundable to confirm booking') }}</div>
                    <input class="input" value="{{ $property->deposit_percent ?? 50 }}" disabled/>
                </div>
                <div style="grid-column: 1 / -1;">
                    <div style="font-size:12px; font-weight:600; margin-bottom:6px;">{{ __('Cancellation policy') }}</div>
                    <textarea class="input" rows="3" style="height:auto; padding:10px;" disabled>{{ $property->cancellation_policy ?? __('Free cancellation up to 7 days before check-in. After that, deposit is non-refundable.') }}</textarea>
                </div>
                <div style="grid-column: 1 / -1; font-size:11.5px; color: var(--ink-3); margin-top:-6px;">
                    {{ __('Read-only here — edit on the property edit page.') }}
                </div>
            </div>

        @elseif ($tab === 'photos')
            <div class="card" style="padding:20px;">
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
                            · {{ __('Hover any photo to set as cover or delete.') }}
                        </div>
                    </div>

                    {{-- Upload form: triggers a hidden file picker, auto-submits on selection --}}
                    <form method="POST"
                          action="{{ route('tenant.properties.photos.store', ['property' => $property->public_id]) }}?tab=photos"
                          enctype="multipart/form-data"
                          style="margin:0;"
                          x-data="{ uploading: false }"
                          @submit="uploading = true">
                        @csrf
                        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
                               x-ref="picker" style="display:none;"
                               @change="$el.form.submit()">
                        <button type="button" class="btn btn-primary btn-sm"
                                @click="$refs.picker.click()"
                                :disabled="uploading"
                                style="display:inline-flex; align-items:center; gap:6px;">
                            <x-icon name="plus" :size="13"/>
                            <span x-show="!uploading">{{ __('Upload photos') }}</span>
                            <span x-show="uploading" x-cloak>{{ __('Uploading…') }}</span>
                        </button>
                    </form>
                </div>

                @if ($property->photos->isEmpty())
                    {{-- Empty state with click-to-upload --}}
                    <button type="button"
                            onclick="this.closest('.card').querySelector('input[type=file]').click()"
                            style="width:100%; padding: 48px 24px; border: 2px dashed var(--line-2); background: var(--bg-elev); border-radius: var(--r-lg); cursor:pointer;
                                   display:flex; flex-direction:column; align-items:center; gap: 10px; color: var(--ink-3);">
                        <div style="font-size:36px; line-height:1;">📷</div>
                        <div style="font-size:14px; font-weight:600; color: var(--ink-2);">{{ __('Drop your first photo') }}</div>
                        <div style="font-size:11.5px;">{{ __('JPG, PNG or WebP — up to 8 MB each. Resized to 2400 px wide on save.') }}</div>
                    </button>
                @else
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">
                        @foreach ($property->photos as $photo)
                            <div style="position:relative; aspect-ratio:4/3; border-radius: var(--r-md); overflow:hidden; border: 1.5px solid {{ $photo->is_hero ? 'var(--primary)' : 'var(--line)' }}; group:hover; background: var(--bg-elev);">
                                <img src="{{ $photo->url() }}" alt=""
                                     style="width:100%; height:100%; object-fit:cover; display:block;"
                                     loading="lazy">

                                {{-- Hero badge (top-left) --}}
                                @if ($photo->is_hero)
                                    <div style="position:absolute; top:8px; left:8px; padding:3px 8px; background: var(--primary); color: var(--primary-ink); font-size:9.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.3);">
                                        ★ {{ __('Cover') }}
                                    </div>
                                @endif

                                {{-- Action overlay (always visible bottom strip — clearer affordance than hover) --}}
                                <div style="position:absolute; bottom:0; left:0; right:0; padding: 6px 8px; background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.65) 100%); display:flex; gap:4px; justify-content:flex-end; align-items:center;">
                                    @unless ($photo->is_hero)
                                        <form method="POST" action="{{ route('tenant.properties.photos.hero', ['property' => $property->public_id, 'photo' => $photo->id]) }}?tab=photos" style="margin:0;">
                                            @csrf
                                            <button type="submit" title="{{ __('Set as cover') }}"
                                                    style="width:26px; height:26px; border:0; background: rgba(255,255,255,0.9); color: var(--ink); border-radius: 4px; cursor:pointer; font-size: 13px; line-height:1; display:inline-flex; align-items:center; justify-content:center;">★</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('tenant.properties.photos.destroy', ['property' => $property->public_id, 'photo' => $photo->id]) }}?tab=photos"
                                          onsubmit="return confirm('{{ addslashes(__('Delete this photo?')) }}');"
                                          style="margin:0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="{{ __('Delete') }}"
                                                style="width:26px; height:26px; border:0; background: rgba(220,40,40,0.92); color: white; border-radius: 4px; cursor:pointer; font-size: 14px; line-height:1; display:inline-flex; align-items:center; justify-content:center;">×</button>
                                    </form>
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
