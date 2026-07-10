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
        if (next === el.value) { syncValidity(el); return; }
        el.value = next;
        try { el.setSelectionRange(next.length, next.length); } catch (e) {}
        syncValidity(el);
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
        syncValidity(e.target);
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
        // A required field keeps its "+60" so the guest always sees the prefix.
        // An optional one empties out, so we never store a lone country code.
        if (isBare(e.target.value)) setValue(e.target, e.target.required ? CC : '');
    });

    // Required fields (the guest's WhatsApp number, the host's phone at signup)
    // show "+60" straight away rather than only once tapped — a grey placeholder
    // reads as "type the whole thing", which is how we got 0-prefixed numbers.
    // Optional fields stay empty until focused.
    // `minlength` only bites on a value the USER typed, so a script-written "+60"
    // would sail through the browser's check and only fail on the server. Mark it
    // invalid ourselves, so the guest gets the native "please fill this in" bubble
    // on the field instead of a round-trip and an error page.
    var BARE_MSG = @json(__('Please enter your phone number after +60.'));
    function syncValidity(el) {
        if (!el.setCustomValidity) return;
        el.setCustomValidity(el.required && isBare(el.value) ? BARE_MSG : '');
    }

    function prefillRequired(root) {
        (root || document).querySelectorAll(SEL).forEach(function (el) {
            if (el.required && el.value.trim() === '') el.value = CC;
            syncValidity(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { prefillRequired(); });
    } else {
        prefillRequired();
    }

    // Livewire/Alpine can inject a phone field after load (a modal, a repeater).
    if (window.MutationObserver) {
        new MutationObserver(function (records) {
            for (var i = 0; i < records.length; i++) {
                var added = records[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var n = added[j];
                    if (n.nodeType !== 1) continue;
                    if (n.matches && n.matches(SEL)) { if (n.required && n.value.trim() === '') n.value = CC; }
                    else if (n.querySelectorAll) prefillRequired(n);
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
</script>
@endonce
