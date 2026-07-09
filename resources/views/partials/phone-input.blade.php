{{--
    Malaysian phone-input behaviour, shared by every form that takes a number.

    Mark an input with `data-phone-input` and include this partial once on the
    page. Focusing an empty field drops in "+60" so the host/guest just types
    the rest; pasting a local number ("0123456789") is rewritten to E.164
    ("+60123456789"); leaving the field untouched clears the bare "+60" again so
    optional fields don't store a country code with no number.

    Deliberately vanilla + delegated on `document`:
      - the auth pages and the public booking page don't all load Alpine;
      - two of the inputs are Livewire `wire:model` fields, whose DOM is morphed
        on re-render, so per-element listeners would be lost.
    After we rewrite the value we re-dispatch `input` (guarded against
    recursion) so Livewire/Alpine see the normalized value, not the raw keystroke.

    A foreign guest can still type their own code: we only prepend +60 when the
    value has no leading "+", so "+65…" and "+1…" are left alone.
--}}
@once
<script>
(function () {
    if (window.__phoneInputBound) return;
    window.__phoneInputBound = true;

    var SEL = '[data-phone-input]';
    var CC  = '+60';
    var busy = false;

    // Keep digits, and at most one leading "+".
    function clean(value) {
        var s = String(value == null ? '' : value).replace(/[^\d+]/g, '');
        return s.charAt(0) === '+'
            ? '+' + s.slice(1).replace(/\+/g, '')
            : s.replace(/\+/g, '');
    }

    function format(value) {
        var s = clean(value);
        if (!s) return '';

        if (s.charAt(0) !== '+') {
            if (s.indexOf('60') === 0)      s = '+' + s;          // 60123… → +60123…
            else if (s.charAt(0) === '0')   s = CC + s.slice(1);  // 0123…  → +60123…
            else                            s = CC + s;           // 123…   → +60123…
        }

        // A Malaysian number never starts with 0 after the country code, so a
        // pasted local number ("+60" + "0123…") collapses to "+60123…".
        s = s.replace(/^\+600+/, CC);

        return s.slice(0, 16); // E.164: "+" + at most 15 digits
    }

    // "+60" on its own carries no number — treat it as empty.
    function isBare(value) { return value === '' || value === '+' || value === CC; }

    function matches(el) { return el && el.matches && el.matches(SEL); }

    function setValue(el, next) {
        if (next === el.value) return;
        el.value = next;
        try { el.setSelectionRange(next.length, next.length); } catch (e) {}
        busy = true;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        busy = false;
    }

    document.addEventListener('focusin', function (e) {
        if (!matches(e.target)) return;
        if (e.target.value.trim() === '') {
            e.target.value = CC;
            try { e.target.setSelectionRange(CC.length, CC.length); } catch (err) {}
        }
    });

    document.addEventListener('input', function (e) {
        if (busy || !matches(e.target)) return;
        var next = format(e.target.value);
        // Deleting everything while still in the field puts "+60" back, so the
        // prefix is always there to type after. Blurring on a bare "+60" still
        // clears the field (see focusout), so optional fields stay empty.
        if (next === '' && document.activeElement === e.target) next = CC;
        setValue(e.target, next);
    });

    document.addEventListener('focusout', function (e) {
        if (!matches(e.target)) return;
        if (isBare(e.target.value)) setValue(e.target, '');
    });
})();
</script>
@endonce
