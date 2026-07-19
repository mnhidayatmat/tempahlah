@php $isBM = app()->getLocale() === 'ms'; @endphp

<x-app-layout :title="__('Send booking form')" :breadcrumbs="[['label' => __('Bookings'), 'url' => route('tenant.bookings.index')]]">

<div class="sf-root" x-data="sendForm(@js($properties), @js($publicUrl), @js($businessName), @js($gatewayReady), @js($isBM), @js(route('tenant.bookings.sign-price')), @js(csrf_token()))">

    <div class="hauz-card sf-card">
        <div class="sf-head">
            <h2 class="sf-title">{{ __('Send a booking form to your guest') }}</h2>
            <p class="sf-sub">
                {{ __('Agreed the dates over WhatsApp? Fill this in and send the guest a link to your own booking page, already filled in. They add their details and confirm — you get the booking.') }}
            </p>
        </div>

        <template x-if="properties.length === 0">
            <p class="sf-empty">{{ __('You have no active homestay yet. Add one first.') }}</p>
        </template>

        <template x-if="properties.length > 0">
        <div>
            <div class="sf-grid">
                <label class="sf-field sf-span2">
                    <span class="sf-label">{{ __('Homestay') }}</span>
                    <select class="input" x-model.number="propertyId" @change="onPropertyChange()">
                        <template x-for="p in properties" :key="p.id">
                            <option :value="p.id" x-text="p.city ? `${p.name} — ${p.city}` : p.name"></option>
                        </template>
                    </select>
                </label>

                <label class="sf-field">
                    <span class="sf-label">{{ __('Check-in') }}</span>
                    <input type="date" class="input" x-model="checkIn" :min="todayStr" @change="reSignAll()">
                </label>

                <label class="sf-field">
                    <span class="sf-label">{{ __('Check-out') }}</span>
                    <input type="date" class="input" x-model="checkOut" :min="minCheckOut()" @change="reSignAll()">
                </label>

                <label class="sf-field">
                    <span class="sf-label">{{ __('Guests') }}</span>
                    <input type="number" class="input" min="1" :max="current.sleeps" x-model.number="guests">
                </label>

                <label class="sf-field">
                    <span class="sf-label">{{ __('Guest WhatsApp number') }} <span class="sf-opt">{{ __('optional') }}</span></span>
                    <input type="tel" class="input" placeholder="+60 12-345 6789" x-model="guestPhone">
                </label>
            </div>

            {{-- Custom stay price. Optional: when set, the guest pays this exact
                 agreed total instead of the calendar rate. Signed server-side
                 (bound to the homestay + dates) so the guest can't edit it down
                 in the URL. Changing the dates drops it and recalculates. --}}
            <div class="sf-price">
                <label class="sf-price-toggle">
                    <input type="checkbox" x-model="fields.stay.on" @change="onToggle('stay')">
                    <span>
                        <strong>{{ __('Set a custom stay price') }}</strong>
                        <em>{{ __('Agreed a special rate for the whole stay? Enter it and the guest pays exactly that.') }}</em>
                    </span>
                </label>

                <div class="sf-price-body" x-show="fields.stay.on" x-cloak>
                    <label class="sf-field">
                        <span class="sf-label">{{ __('Price for the whole stay (RM)') }}</span>
                        <div class="sf-price-input">
                            <span class="sf-price-rm">RM</span>
                            <input type="number" class="input" min="0" step="0.01" inputmode="decimal"
                                   placeholder="0.00" x-model.number="fields.stay.amount" @input="scheduleSign('stay')">
                        </div>
                        <p class="sf-hint" x-show="!validRange()" x-cloak>
                            {{ __('Pick the check-in and check-out dates above first — the price is locked to those dates.') }}
                        </p>
                        <p class="sf-hint" x-show="validRange() && fields.stay.state === 'ready'" x-cloak>
                            {{ __('Stay total') }} <strong>RM <span x-text="money(fields.stay.sig.price)"></span></strong>
                            <template x-if="nights() > 0">
                                <span>· {{ __('about') }} RM <span x-text="money(fields.stay.sig.price / nights())"></span> / {{ __('night') }}</span>
                            </template>
                        </p>
                        <p class="sf-hint sf-price-err" x-show="validRange() && fields.stay.state === 'error'" x-cloak>
                            {{ __('Could not lock this price. Please try again.') }}
                        </p>
                    </label>
                </div>
            </div>

            {{-- Custom pay-now / booking fee. Optional: the amount the guest pays
                 immediately to confirm (defaults to the homestay's booking fee,
                 usually RM 100). The stay total is unchanged — only how much is
                 collected now vs. as the balance. Signed the same way. --}}
            <div class="sf-price">
                <label class="sf-price-toggle">
                    <input type="checkbox" x-model="fields.paynow.on" @change="onToggle('paynow')">
                    <span>
                        <strong>{{ __('Set the pay-now amount') }}</strong>
                        <em>{{ __('How much the guest pays now to confirm (default is your booking fee). The rest becomes the balance.') }}</em>
                    </span>
                </label>

                <div class="sf-price-body" x-show="fields.paynow.on" x-cloak>
                    <label class="sf-field">
                        <span class="sf-label">{{ __('Pay-now amount (RM)') }}</span>
                        <div class="sf-price-input">
                            <span class="sf-price-rm">RM</span>
                            <input type="number" class="input" min="0" step="0.01" inputmode="decimal"
                                   placeholder="200.00" x-model.number="fields.paynow.amount" @input="scheduleSign('paynow')">
                        </div>
                        <p class="sf-hint" x-show="!validRange()" x-cloak>
                            {{ __('Pick the check-in and check-out dates above first.') }}
                        </p>
                        <p class="sf-hint" x-show="validRange() && fields.paynow.state === 'ready'" x-cloak>
                            {{ __('Guest pays now') }} <strong>RM <span x-text="money(fields.paynow.sig.price)"></span></strong>
                        </p>
                        <p class="sf-hint sf-price-err" x-show="validRange() && fields.paynow.state === 'error'" x-cloak>
                            {{ __('Could not lock this amount. Please try again.') }}
                        </p>
                    </label>
                </div>
            </div>

            <label class="sf-check">
                <input type="checkbox" x-model="manual">
                <span>
                    <strong>{{ __('Ask the guest to pay manually') }}</strong>
                    <em x-show="gatewayReady">{{ __('Bank transfer or cash. Leave unticked to let them pay online instead.') }}</em>
                    <em x-show="!gatewayReady" x-cloak>{{ __('You have no online gateway connected, so payment is manual either way.') }}</em>
                </span>
            </label>

            {{-- The host is quoting from memory; the calendar lives elsewhere. If
                 the range they type is already sold, say so here rather than let
                 the guest discover it. --}}
            <div class="sf-warn" x-show="rangeConflict()" x-cloak>
                {{ __('Those nights are already booked. The guest will have to pick different dates.') }}
            </div>
            <div class="sf-warn" x-show="checkIn && checkOut && !validRange()" x-cloak>
                {{ __('Check-out must be after check-in.') }}
            </div>

            <div class="sf-link-wrap">
                <span class="sf-label">{{ __('Booking form link') }}</span>
                <div class="sf-link-row">
                    <input type="text" class="input sf-link" readonly :value="link()" x-ref="link" @focus="$event.target.select()">
                    <button type="button" class="btn" @click="copy()">
                        <span x-show="!copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                    </button>
                </div>
                <p class="sf-hint" x-show="!hasCustomPrice()">{{ __('Anyone with this link can book. The guest can still change the dates — the price recalculates and availability is re-checked when they submit.') }}</p>
                <p class="sf-hint" x-show="hasCustomPrice()" x-cloak>{{ __('The guest pays your set price for these dates and can’t change it. If they pick different dates, the price recalculates automatically.') }}</p>
            </div>

            <div class="sf-actions">
                <a class="btn btn-primary sf-wa" :href="waLink()" target="_blank" rel="noopener">
                    <span x-text="guestPhone.trim() ? '{{ __('Send on WhatsApp') }}' : '{{ __('Share on WhatsApp') }}'"></span>
                </a>
                <a class="btn" :href="link()" target="_blank" rel="noopener">{{ __('Preview form') }}</a>
            </div>

            <details class="sf-msg">
                <summary>{{ __('Message the guest will receive') }}</summary>
                <pre class="sf-msg-body" x-text="message()"></pre>
            </details>
        </div>
        </template>
    </div>
</div>

<style>
    .sf-root { max-width: 720px; }
    .sf-card { padding: 24px; }
    .sf-head { margin-bottom: 20px; }
    .sf-title { font-size: 18px; font-weight: 650; margin: 0 0 6px; }
    .sf-sub { color: var(--ink-3); font-size: 13.5px; margin: 0; line-height: 1.55; }
    .sf-empty { color: var(--ink-3); margin: 0; }
    .sf-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .sf-grid > * { min-width: 0; }
    .sf-span2 { grid-column: 1 / -1; }
    .sf-field { display: flex; flex-direction: column; gap: 6px; }
    .sf-label { font-size: 12px; font-weight: 600; color: var(--ink-2); }
    .sf-opt { font-weight: 400; color: var(--ink-4); }
    .sf-check { display: flex; gap: 10px; align-items: flex-start; margin-top: 18px;
                padding: 14px; border: .5px solid var(--line); border-radius: var(--r-md); background: var(--bg-elev); }
    .sf-check input { margin-top: 3px; }
    .sf-check span { display: flex; flex-direction: column; gap: 3px; font-size: 13.5px; }
    .sf-check em { font-style: normal; color: var(--ink-3); font-size: 12.5px; }
    .sf-price { margin-top: 14px; }
    .sf-price-toggle { display: flex; gap: 10px; align-items: flex-start;
                       padding: 14px; border: .5px solid var(--line); border-radius: var(--r-md); background: var(--bg-elev); }
    .sf-price-toggle input { margin-top: 3px; }
    .sf-price-toggle span { display: flex; flex-direction: column; gap: 3px; font-size: 13.5px; }
    .sf-price-toggle em { font-style: normal; color: var(--ink-3); font-size: 12.5px; }
    .sf-price-body { margin-top: 12px; }
    .sf-price-input { position: relative; display: flex; align-items: center; }
    .sf-price-rm { position: absolute; left: 12px; font-size: 13px; color: var(--ink-4); font-weight: 600; pointer-events: none; }
    .sf-price-input .input { padding-left: 38px; }
    .sf-price-err { color: var(--err); }
    .sf-warn { margin-top: 12px; padding: 10px 12px; border-radius: var(--r-md);
               background: var(--warn-tint, #fdf3e0); color: var(--ink-2); font-size: 13px;
               border: .5px solid var(--warn); }
    .sf-link-wrap { margin-top: 20px; display: flex; flex-direction: column; gap: 6px; }
    .sf-link-row { display: flex; gap: 8px; }
    .sf-link { flex: 1; min-width: 0; font-family: var(--mono, monospace); font-size: 12.5px; }
    .sf-hint { color: var(--ink-4); font-size: 12px; margin: 2px 0 0; line-height: 1.5; }
    .sf-actions { display: flex; gap: 10px; margin-top: 18px; }
    .sf-wa { flex: 1; text-align: center; }
    .sf-msg { margin-top: 18px; }
    .sf-msg summary { cursor: pointer; font-size: 13px; color: var(--ink-3); }
    .sf-msg-body { margin: 10px 0 0; padding: 12px; background: var(--bg-sunk); border-radius: var(--r-md);
                   font-size: 12.5px; white-space: pre-wrap; word-break: break-word; font-family: inherit; color: var(--ink-2); }

    @media (max-width: 640px) {
        .sf-card { padding: 16px 14px; }
        .sf-grid { grid-template-columns: 1fr; }
        .sf-actions { flex-direction: column-reverse; }
        .sf-actions .btn { width: 100%; }
        .input { font-size: 16px; min-height: 44px; } /* no iOS zoom on focus */
    }
</style>

<script>
    function sendForm(properties, publicUrl, businessName, gatewayReady, isBM, signUrl, csrf) {
        return {
            properties, publicUrl, businessName, gatewayReady, isBM, signUrl, csrf,
            propertyId: properties[0]?.id ?? null,
            checkIn: '',
            checkOut: '',
            guests: properties[0]?.default_guests ?? 2,
            guestPhone: '',
            manual: true,
            copied: false,

            /* Two optional host-set amounts, each signed server-side and bound
               to the current property + dates:
                 - stay:   the whole-stay price   → CreateBooking base_amount
                 - paynow: the deposit / pay-now  → CreateBooking deposit_amount
               Per field: `on` toggle, `amount` typed value, `sig` the verified
               {price, sig, propertyId, checkIn, checkOut} (null until it checks
               out), `state` = idle|signing|ready|error. */
            fields: {
                stay:   { on: false, amount: null, sig: null, state: 'idle' },
                paynow: { on: false, amount: null, sig: null, state: 'idle' },
            },
            _timers: { stay: null, paynow: null },

            get todayStr() {
                const d = new Date();
                return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            },
            get current() {
                return this.properties.find(p => p.id === this.propertyId) || { sleeps: 20, booked: [], name: '', rate: 0 };
            },

            onPropertyChange() {
                this.guests = Math.min(this.current.default_guests || 2, this.current.sleeps || 99);
                this.reSignAll();
            },

            /* ── Signed custom amounts ────────────────────────────────
               Each signature is bound to (property, check-in, check-out,
               amount, purpose), so any change to those must re-sign. We debounce
               and only surface a sig that still matches the current inputs — a
               stale sig is never put in the link. */
            onToggle(key) {
                const f = this.fields[key];
                if (!f.on) { f.amount = null; f.sig = null; f.state = 'idle'; }
                else { this.scheduleSign(key); }
            },

            reSignAll() { this.scheduleSign('stay'); this.scheduleSign('paynow'); },

            scheduleSign(key) {
                const f = this.fields[key];
                f.sig = null;                       // invalidate immediately
                clearTimeout(this._timers[key]);
                if (!f.on) { f.state = 'idle'; return; }
                const amt = Number(f.amount);
                if (!this.validRange() || !this.propertyId || !(amt > 0)) { f.state = 'idle'; return; }
                f.state = 'signing';
                this._timers[key] = setTimeout(() => this.refreshSign(key), 350);
            },

            refreshSign(key) {
                const f = this.fields[key];
                const propertyId = this.propertyId, checkIn = this.checkIn, checkOut = this.checkOut;
                const amt = Number(f.amount);
                const purpose = key; // 'stay' | 'paynow' — matches QuotedPrice::PURPOSES
                fetch(this.signUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ property_id: propertyId, check_in: checkIn, check_out: checkOut, price: amt, purpose }),
                }).then(r => r.ok ? r.json() : Promise.reject(r))
                  .then(data => {
                    // Ignore a response that raced behind a newer edit.
                    if (this.propertyId !== propertyId || this.checkIn !== checkIn || this.checkOut !== checkOut || Number(f.amount) !== amt) return;
                    f.sig = { price: data.price, sig: data.sig, propertyId, checkIn, checkOut };
                    f.state = 'ready';
                  })
                  .catch(() => { f.sig = null; f.state = 'error'; });
            },

            /* A signed amount is in play only when its sig matches the exact
               inputs currently in the form. */
            has(key) {
                const f = this.fields[key];
                const s = f.sig;
                return !!(s && f.on
                    && s.propertyId === this.propertyId
                    && s.checkIn === this.checkIn
                    && s.checkOut === this.checkOut);
            },
            hasCustomPrice() { return this.has('stay'); },
            hasPayNow() { return this.has('paynow'); },

            minCheckOut() {
                if (!this.checkIn) return this.todayStr;
                const d = new Date(this.checkIn + 'T00:00:00');
                d.setDate(d.getDate() + 1);
                return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            },

            validRange() {
                return !!(this.checkIn && this.checkOut && this.checkOut > this.checkIn);
            },

            nights() {
                if (!this.validRange()) return 0;
                const a = new Date(this.checkIn + 'T00:00:00');
                const b = new Date(this.checkOut + 'T00:00:00');
                return Math.round((b - a) / 86400000);
            },

            /* Any booked night inside the half-open range [checkIn, checkOut)? */
            rangeConflict() {
                if (!this.validRange()) return false;
                const booked = new Set(this.current.booked || []);
                const cur = new Date(this.checkIn + 'T00:00:00');
                const end = new Date(this.checkOut + 'T00:00:00');
                while (cur < end) {
                    const k = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-${String(cur.getDate()).padStart(2, '0')}`;
                    if (booked.has(k)) return true;
                    cur.setDate(cur.getDate() + 1);
                }
                return false;
            },

            link() {
                const q = new URLSearchParams();
                if (this.propertyId) q.set('property_id', this.propertyId);
                if (this.checkIn) q.set('check_in', this.checkIn);
                if (this.validRange()) q.set('check_out', this.checkOut);
                if (this.guests) q.set('guests', this.guests);
                q.set('pay', this.manual ? 'manual' : 'gateway');
                // Carry each signed amount only when its sig matches the current
                // form inputs — never a stale signature.
                if (this.hasCustomPrice()) {
                    q.set('price', this.fields.stay.sig.price);
                    q.set('psig', this.fields.stay.sig.sig);
                }
                if (this.hasPayNow()) {
                    q.set('fee', this.fields.paynow.sig.price);
                    q.set('fsig', this.fields.paynow.sig.sig);
                }
                return `${this.publicUrl}?${q.toString()}`;
            },

            money(n) {
                return Number(n || 0).toLocaleString(this.isBM ? 'ms-MY' : 'en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            message() {
                const n = this.nights();
                const name = this.current.name;
                // Custom price (when set) is the agreed total the guest pays;
                // otherwise fall back to the calendar estimate.
                const total = this.hasCustomPrice() ? Number(this.fields.stay.sig.price) : n * (this.current.rate || 0);
                const payNow = this.hasPayNow() ? Number(this.fields.paynow.sig.price) : null;

                if (this.isBM) {
                    let m = `Salam! Ini borang tempahan untuk ${name}`;
                    if (n > 0) m += `, ${this.checkIn} hingga ${this.checkOut} (${n} malam)`;
                    m += `.`;
                    if (n > 0 && total > 0) m += `\nAnggaran: RM ${this.money(total)}`;
                    if (payNow !== null) m += `\nBayaran pengesahan sekarang: RM ${this.money(payNow)}`;
                    m += `\n\nIsi maklumat anda di sini:\n${this.link()}`;
                    if (this.manual) m += `\n\nBayaran secara pindahan bank — butiran akan dipaparkan selepas anda hantar.`;
                    return m;
                }

                let m = `Hi! Here's the booking form for ${name}`;
                if (n > 0) m += `, ${this.checkIn} to ${this.checkOut} (${n} night${n === 1 ? '' : 's'})`;
                m += `.`;
                if (n > 0 && total > 0) m += `\nEstimated total: RM ${this.money(total)}`;
                if (payNow !== null) m += `\nPay now to confirm: RM ${this.money(payNow)}`;
                m += `\n\nFill in your details here:\n${this.link()}`;
                if (this.manual) m += `\n\nPayment is by bank transfer — the details show up once you submit.`;
                return m;
            },

            waLink() {
                const phone = this.guestPhone.replace(/\D/g, '');
                const text = encodeURIComponent(this.message());
                return phone ? `https://wa.me/${phone}?text=${text}` : `https://wa.me/?text=${text}`;
            },

            copy() {
                const el = this.$refs.link;
                el.select();
                navigator.clipboard.writeText(el.value).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                });
            },
        };
    }
</script>
</x-app-layout>
