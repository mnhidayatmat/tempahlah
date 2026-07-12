@props([
    'state' => '',
    'district' => '',   // stored in the property's `city` column
    'postcode' => '',
])
@php
    $bm = app()->getLocale() === 'ms';
    $map = config('districts');
    $curState = old('state', $state);
    $curDistrict = old('city', $district);
    $curPostcode = old('postcode', $postcode);
@endphp

{{-- State + District cascade + postcode. The district is submitted as `city`
     so it matches the marketplace search filter (state = state, district = city). --}}
<div class="loc-picker" data-districts='@json($map)'
     data-all-label="{{ $bm ? '— Pilih daerah —' : '— Select district —' }}"
     style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px;">
    <div>
        <label class="kicker" style="display:block; margin-bottom:6px;">{{ $bm ? 'Negeri' : 'State' }} *</label>
        <select class="input" name="state" data-loc="state" required>
            <option value="">{{ $bm ? '— Pilih negeri —' : '— Select state —' }}</option>
            @foreach (array_keys($map) as $st)
                <option value="{{ $st }}" @selected($curState === $st)>{{ $st }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="kicker" style="display:block; margin-bottom:6px;">{{ $bm ? 'Daerah' : 'District' }} *</label>
        <select class="input" name="city" data-loc="district" required>
            <option value="">{{ $bm ? '— Pilih daerah —' : '— Select district —' }}</option>
            @foreach (($map[$curState] ?? []) as $d)
                <option value="{{ $d }}" @selected($curDistrict === $d)>{{ $d }}</option>
            @endforeach
        </select>
    </div>
    <div style="grid-column: 1 / -1; max-width: 200px;">
        <label class="kicker" style="display:block; margin-bottom:6px;">{{ $bm ? 'Poskod' : 'Postcode' }} *</label>
        <input class="input" type="text" name="postcode" data-loc="postcode"
               value="{{ $curPostcode }}" required inputmode="numeric" pattern="[0-9]{5}" maxlength="5"
               placeholder="{{ $bm ? 'cth. 86000' : 'e.g. 86000' }}">
    </div>
</div>

@once
<script>
    document.querySelectorAll('.loc-picker').forEach(function (pick) {
        var map = JSON.parse(pick.getAttribute('data-districts') || '{}');
        var allLabel = pick.getAttribute('data-all-label') || '— Select district —';
        var st = pick.querySelector('[data-loc="state"]');
        var di = pick.querySelector('[data-loc="district"]');
        if (!st || !di) return;
        st.addEventListener('change', function () {
            var list = map[st.value] || [];
            var keep = di.value;
            di.innerHTML = '';
            var o0 = document.createElement('option');
            o0.value = ''; o0.textContent = allLabel; di.appendChild(o0);
            list.forEach(function (d) {
                var o = document.createElement('option');
                o.value = d; o.textContent = d;
                if (d === keep) o.selected = true;
                di.appendChild(o);
            });
        });
    });
</script>
@endonce
