{{--
    Reusable amenity checkbox grid.
    @param array  $amenityGroups       Output of PropertyController::amenityGroups()
    @param array  $selectedAmenityIds  IDs that should start checked (default: [])
    @param string $title                Section heading
--}}
@php
    $bm = app()->getLocale() === 'ms';
    $selectedAmenityIds = $selectedAmenityIds ?? [];
    $title = $title ?? __('Facilities & amenities');
@endphp

<div>
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
        <label class="kicker" style="display:block; margin:0;">{{ $title }}</label>
        <span style="font-size:11px; color: var(--ink-3);">{{ __('Tick anything this property offers') }}</span>
    </div>

    <div style="display:flex; flex-direction:column; gap:18px; margin-top:8px;">
        @foreach ($amenityGroups as $catKey => $group)
            <div>
                <div style="font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color: var(--ink-3); margin-bottom:8px;">
                    {{ $group['label'] }}
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:8px;">
                    @foreach ($group['items'] as $a)
                        @php $checked = in_array($a->id, (array) old('amenities', $selectedAmenityIds)); @endphp
                        <label style="cursor:pointer; display:flex; align-items:center; gap:9px;
                                      padding: 10px 12px;
                                      border: 1.5px solid {{ $checked ? 'var(--primary)' : 'var(--line)' }};
                                      background: {{ $checked ? 'var(--primary-tint)' : 'var(--bg-elev)' }};
                                      color: {{ $checked ? 'var(--primary-deep)' : 'var(--ink-2)' }};
                                      border-radius: var(--r-md);
                                      transition: all 120ms;
                                      font-size:12.5px; font-weight: {{ $checked ? '600' : '500' }};"
                               onmouseover="if(!this.querySelector('input').checked){this.style.borderColor='var(--ink-4)';}"
                               onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='var(--line)';}">
                            <input type="checkbox" name="amenities[]" value="{{ $a->id }}"
                                   {{ $checked ? 'checked' : '' }}
                                   style="margin:0; accent-color: var(--primary); width:14px; height:14px; flex-shrink:0;"
                                   onchange="
                                       const lbl=this.closest('label');
                                       lbl.style.borderColor = this.checked ? 'var(--primary)' : 'var(--line)';
                                       lbl.style.background  = this.checked ? 'var(--primary-tint)' : 'var(--bg-elev)';
                                       lbl.style.color       = this.checked ? 'var(--primary-deep)' : 'var(--ink-2)';
                                       lbl.style.fontWeight  = this.checked ? '600' : '500';
                                   ">
                            <span style="font-size:16px; line-height:1; flex-shrink:0;">{{ $a->icon }}</span>
                            <span style="flex:1; line-height:1.25;">{{ $bm ? $a->label_bm : $a->label_en }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
