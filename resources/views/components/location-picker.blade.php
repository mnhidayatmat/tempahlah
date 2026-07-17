@props([
    'state' => '',
    'district' => '',   // stored in the property's `city` column
    'postcode' => '',
])
@php
    $bm = app()->getLocale() === 'ms';
    $map = config('districts');
    $postcodeMap = config('postcodes');
    $curState = old('state', $state);
    $curDistrict = old('city', $district);
    $curPostcode = old('postcode', $postcode);
    $curPostcodes = $postcodeMap[$curState][$curDistrict] ?? [];
@endphp

{{-- State + District cascade + postcode. The district is submitted as `city`
     so it matches the marketplace search filter (state = state, district = city).
     Poskod is a dropdown that fills from the chosen daerah — no manual typing. --}}
<div class="loc-picker"
     data-districts='@json($map)'
     data-postcodes='@json($postcodeMap)'
     data-district-label="{{ $bm ? '— Pilih daerah —' : '— Select district —' }}"
     data-postcode-label="{{ $bm ? '— Pilih poskod —' : '— Select postcode —' }}"
     data-postcode-first="{{ $bm ? '— Pilih daerah dahulu —' : '— Select district first —' }}"
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
    <div style="grid-column: 1 / -1; max-width: 240px;">
        <label class="kicker" style="display:block; margin-bottom:6px;">{{ $bm ? 'Poskod' : 'Postcode' }} *</label>
        <select class="input" name="postcode" data-loc="postcode" required>
            @if ($curDistrict === '')
                <option value="">{{ $bm ? '— Pilih daerah dahulu —' : '— Select district first —' }}</option>
            @else
                <option value="">{{ $bm ? '— Pilih poskod —' : '— Select postcode —' }}</option>
            @endif
            {{-- Keep an existing/legacy postcode selectable even if it's not in
                 this daerah's derived list, so editing never loses a saved value. --}}
            @if ($curPostcode !== '' && ! in_array($curPostcode, $curPostcodes, true))
                <option value="{{ $curPostcode }}" selected>{{ $curPostcode }}</option>
            @endif
            @foreach ($curPostcodes as $pc)
                <option value="{{ $pc }}" @selected($curPostcode === $pc)>{{ $pc }}</option>
            @endforeach
        </select>
    </div>
</div>

@once
<script>
    document.querySelectorAll('.loc-picker').forEach(function (pick) {
        var districts = JSON.parse(pick.getAttribute('data-districts') || '{}');
        var postcodes = JSON.parse(pick.getAttribute('data-postcodes') || '{}');
        var districtLabel = pick.getAttribute('data-district-label') || '— Select district —';
        var postcodeLabel = pick.getAttribute('data-postcode-label') || '— Select postcode —';
        var postcodeFirst = pick.getAttribute('data-postcode-first') || '— Select district first —';
        var st = pick.querySelector('[data-loc="state"]');
        var di = pick.querySelector('[data-loc="district"]');
        var po = pick.querySelector('[data-loc="postcode"]');
        if (!st || !di || !po) return;

        function fillPostcodes(keepValue) {
            var list = (postcodes[st.value] || {})[di.value] || [];
            po.innerHTML = '';
            var o0 = document.createElement('option');
            o0.value = '';
            o0.textContent = di.value ? postcodeLabel : postcodeFirst;
            po.appendChild(o0);
            // preserve a legacy value that isn't in the derived list
            if (keepValue && list.indexOf(keepValue) === -1) {
                var ok = document.createElement('option');
                ok.value = keepValue; ok.textContent = keepValue; ok.selected = true;
                po.appendChild(ok);
            }
            list.forEach(function (pc) {
                var o = document.createElement('option');
                o.value = pc; o.textContent = pc;
                if (pc === keepValue) o.selected = true;
                po.appendChild(o);
            });
        }

        st.addEventListener('change', function () {
            var list = districts[st.value] || [];
            var keep = di.value;
            di.innerHTML = '';
            var o0 = document.createElement('option');
            o0.value = ''; o0.textContent = districtLabel; di.appendChild(o0);
            list.forEach(function (d) {
                var o = document.createElement('option');
                o.value = d; o.textContent = d;
                if (d === keep) o.selected = true;
                di.appendChild(o);
            });
            // state changed → the poskod list depends on the (possibly reset) district
            fillPostcodes(null);
        });

        di.addEventListener('change', function () {
            fillPostcodes(null);
        });
    });
</script>
@endonce
