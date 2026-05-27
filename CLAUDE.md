# HomestayMY — Project Context for Claude Code

> **READ THIS FIRST** at the start of every session. This file is the source of truth for project state, decisions, and what's left to do.

---

## 🎯 What we're building

A multi-tenant **SaaS + Marketplace** for Malaysian homestay operators.

- **SaaS side**: each tenant gets a private booking page (or custom domain on paid tier) where they take direct bookings. No commission on direct bookings.
- **Marketplace side**: paid tenants opt-in to list properties on `homestaymy.com`, where guests browse/book. Platform takes **3% commission** on marketplace bookings only.
- **Three clients**: Web (Blade), PWA (same Blade + service worker), Native mobile (Flutter, tenant + staff only — guests use PWA).
- **Pricing**: Free RM 0 (1 property / 3 rooms / 20 bookings per month), Paid **RM 49/month** (unlimited + Toyyibpay + custom invoices + WhatsApp + dynamic pricing + 5 staff + custom domain + marketplace listing). 7-day trial of paid.

---

## 🔒 Locked Tech Stack

| Layer | Choice | Reason |
|---|---|---|
| Framework | **Laravel 12.58** | Latest stable |
| PHP | 8.3+ (8.4 dev) | Laravel 12 requirement |
| Web templating | **Blade** | SEO + simplicity |
| Reactive UI | **Livewire 4** (tenant dashboard + Filament admin) | No SPA, Blade-first |
| Light interactivity | **Alpine.js + Tailwind** | Public marketplace, guest pages |
| Admin panel | **Filament 5.6** (super-tenant admin) | Note: design doc said "3", upgraded to 5 for Livewire 4 compat |
| Database | **MySQL 8** (prod) — SQLite (local dev — see "Database notes") | User specified MySQL |
| Auth (web) | Session + CSRF | Default |
| Auth (API) | **Laravel Sanctum 4.3** | Flutter mobile + paid-tier API access |
| RBAC | **Spatie Permission 7** | Roles: super_admin, owner, manager, cleaner, laundry, guest |
| Feature flags | **Laravel Pennant** | Free/paid tier gating |
| Email | **AWS SES** | User specified |
| Storage | **AWS S3 + CloudFront** | Photos, invoice PDFs, KYC docs |
| Push | **Firebase Cloud Messaging** | Tenant + staff Flutter app |
| PDF | **barryvdh/laravel-dompdf 3.1** | Invoices/receipts |
| Image | **Intervention Image 4** | Resize, watermark, EXIF strip |
| Guest payments | **Toyyibpay** | One-off bills (deposit, balance) |
| Subscription billing | **Manual v1** (monthly invoice + payment link) → Billplz recurring v2 | Toyyibpay doesn't support recurring |
| Channel sync | **Google Calendar 2-way at launch**, Airbnb/Booking iCal v1.5 | User confirmed |
| Search | MySQL full-text v1 → Meilisearch v2 if traffic warrants | |
| Queue | Redis (ElastiCache) prod / database driver dev | |
| Testing | Pest (TBD — to confirm in Phase 2) | |

### Database notes
- Local dev: `.env` uses `DB_CONNECTION=sqlite` (zero-setup, MySQL was returning Access denied for root)
- Production: `DB_CONNECTION=mysql` (template in `.env.example`)
- All migrations MUST be MySQL-compatible (no SQLite-specific features like `WITHOUT ROWID`)
- When user provides MySQL credentials: switch local `.env` and run `php artisan migrate:fresh --seed`

---

## 🧱 Multi-tenancy model

- **Row-level isolation** via `tenant_id` discriminator column on every tenant-owned table
- `BelongsToTenant` trait on Eloquent models adds global scope + auto-fills `tenant_id`
- **Tenant context middleware** resolves current tenant from session (web) or Sanctum token (API)
- **Cross-tenant tables** (no `tenant_id`): `users` (guests), `marketplace_listings`, `guest_blacklist_entries`, `disputes`, `super_admins`, `audit_logs`
- Why row-level not schema-per-tenant: marketplace requires cross-tenant queries

---

## 👤 Roles & permissions

| Role | Scope | Access |
|---|---|---|
| `super_admin` | platform-wide | Filament admin panel — KYC review, blacklist appeals, disputes, MRR |
| `owner` | per tenant | Full tenant access incl. finances, staff, subscription |
| `manager` | per tenant | Bookings, calendar, no finance |
| `cleaner` | per tenant | Own assigned cleaning tasks only |
| `laundry` | per tenant | Own assigned laundry tasks only |
| `guest` | global | Books, reviews, gets reviewed/blacklisted |

A user record can serve multiple tenants via `tenant_users` pivot with role.

---

## 💰 Free vs Paid tier

| Capability | Free | Paid (RM 49/mo + 3% marketplace commission) |
|---|---|---|
| Properties | 1 | Unlimited |
| Rooms / property | 3 | Unlimited |
| Bookings / month | 20 | Unlimited |
| Staff accounts | 1 owner | 5 (any role mix) |
| Private booking page | ✓ | ✓ + custom domain |
| Marketplace listing | ❌ | ✓ opt-in per property |
| Payment gateway | Manual only | Toyyibpay + manual |
| Auto reminders | ❌ | ✓ (X days before checkin) |
| Google Calendar sync | 1-way | 2-way |
| Airbnb/Booking iCal | ❌ | ✓ (v1.5) |
| WhatsApp | Click-to-WA link | Business API auto-send |
| Custom invoice/receipt PDF | ❌ basic on-screen | ✓ tenant-designed, auto-emailed + in-app |
| Email branding | Platform | Tenant logo |
| Cleaner/laundry auto-scheduling | Manual | Auto from bookings |
| Maintenance + inventory alerts | Basic | Full |
| Dynamic pricing | ❌ | ✓ |
| Reports & exports | 30 days, screen | Unlimited, CSV/PDF |
| Reviews + blacklist | ✓ | ✓ + public reply |
| API access (Sanctum) | ❌ | ✓ |
| Trial of paid | 7 days, no card needed | — |

---

## 🇲🇾 Malaysia-specific requirements

- **SST 8%** on accommodation if tenant exceeds threshold (configurable per tenant)
- **Tourism Tax RM 10/night** for foreign guests at registered accommodations
- **MOTAC license** field on properties — optional, with "MOTAC verified ✓" badge if filled and approved
- **PDPA compliance**: cookie consent, data export, right to be forgotten, audit log
- **Languages**: BM + EN at launch with toggle; URL-prefixed routes `/ms/...` and `/en/...` for SEO
- **Currency**: RM only at launch (USD/SGD auto-convert deferred to v2)
- **Geographic**: all Malaysia from day 1
- **Tenant persona**: user-friendly, mobile-first, BM-primary (must work for kampung homestay owners AND boutique operators)

---

## 🗺️ Phased roadmap

Tasks tracked via TaskCreate/TaskList. **Always run TaskList at the start of each session to see current state.**

### v1 (launch)
- **Phase 1**: Laravel scaffold + packages + CLAUDE.md ✅ in progress
- **Phase 2**: Multi-tenancy foundation + auth + RBAC
- **Phase 3**: Properties + rooms + calendar + pricing
- **Phase 4**: Booking flow (direct) + payments (manual + Toyyibpay)
- **Phase 5**: Invoices/receipts + email/WhatsApp comms
- **Phase 6**: Operations modules (cleaning, laundry, maintenance, inventory)
- **Phase 7**: Reviews + guest blacklist
- **Phase 8**: Marketplace (listings, search, guest auth, commission, payouts)
- **Phase 9**: Google Calendar 2-way sync
- **Phase 10**: Reports + analytics
- **Phase 11**: Filament 5 super-tenant admin
- **Phase 12**: Sanctum API (full surface for Flutter)
- **Phase 13**: PWA + i18n + SEO + PDPA

### v1.5 (month 2-3 after launch)
- Airbnb iCal sync
- Booking.com iCal sync
- WhatsApp Business API auto-send
- Dynamic pricing engine
- Inventory low-stock alerts

### v2 (month 4-6)
- Billplz recurring subscription billing
- Currency auto-convert (USD/SGD)
- Featured marketplace placements
- Guest discovery mobile app
- API access for paid tenants
- Multi-currency payouts

---

## 🔐 Critical config notes

### Environment files
- `.env` — local dev (SQLite, no real credentials)
- `.env.example` — template (MySQL + all integration vars)

### Required external accounts (set when going to staging/prod)
- AWS account with SES, S3, RDS, ElastiCache provisioned (region `ap-southeast-1`)
- Toyyibpay merchant account (sandbox: `dev.toyyibpay.com`)
- Google Cloud project with Calendar API enabled + OAuth client
- Firebase project for FCM
- WhatsApp Business API approval (paid tier)
- Billplz merchant account (v2)

### Security baseline
- All KYC fields (MyKad, bank account) encrypted at app layer (AES-256 via Laravel cast)
- S3 buckets PRIVATE, signed URLs only (5-min expiry)
- Rate limits per `RateLimiter::for(...)` — see `app/Providers/AppServiceProvider.php` boot()
- Webhook signature verification required (Toyyibpay HMAC-SHA256)
- HTTPS only via CloudFront

---

## 📐 Conventions

### Code
- PSR-12 + Laravel default Pint config
- Eloquent over query builder unless perf-critical
- Models in `app/Models/`, traits in `app/Models/Concerns/`
- Form requests in `app/Http/Requests/{Web,Api}/`
- Policies in `app/Policies/`
- Livewire components in `app/Livewire/`
- Filament resources in `app/Filament/Admin/Resources/`
- Filament panel: route prefix `/super-admin`

### Multi-tenancy
- Every tenant-scoped model uses `BelongsToTenant` trait
- Never query `tenant_id` directly — let global scope handle it
- For super-admin cross-tenant queries: explicit `Model::withoutGlobalScope(BelongsToTenant::class)`

### Migrations
- Every tenant-scoped table starts with `tenant_id` foreign key
- Composite index `(tenant_id, ...)` on common query columns
- Use `ulid()` for public-facing IDs (bookings, properties), bigint auto-inc for internal
- Foreign keys: `cascadeOnDelete()` only when child can't exist without parent

### Naming
- Routes: `tenant.bookings.index`, `marketplace.search`, `admin.tenants.show`
- Permissions: `bookings.view`, `bookings.create`, `invoices.refund`, etc.
- Feature flags: `feature_paid_tier`, `feature_whatsapp_business`, `feature_marketplace_listing`

---

## 🚦 How to resume work in a new session

1. **Read this file first.**
2. Run `TaskList` to see current state.
3. Find the lowest-numbered `pending` or `in_progress` task.
4. Mark it `in_progress` if not already, then continue.
5. Update tasks as you complete them.
6. **Update this file** at the end of significant work — especially the "Current state" section below.

---

## 📊 Current state (update as you go)

> Last updated: 2026-05-27 — **Per-tenant brand & theme customization** under `/dashboard/settings` — tenants pick Primary / Secondary / Accent (with 6 curated presets, live preview, and auto-derived hover/deep/tint variants) and the palette flows through both the dashboard chrome and their public subdomain booking page via injected CSS variable overrides. **Wafa-style mobile redesign of the tenant subdomain landing page** (`{slug}.homestaymy.com/`). Replaced the editorial "Reservation Notebook" look with the phone-frame design from the user-supplied `Homestay.zip` reference (`Wafa Mobile Booking.html` + `pasted-1777721244268-0.png`). Now: hero banner with property cover gradient + locale + WhatsApp pills overlaid, orange-bar property "package" cards with notched-circle RM-pill on the right (kerf detail straight from the mock), dark "Ketuk mana-mana tarikh untuk tempah ↓" prompt strip, **Sunday-first** calendar (mock parity) with big rounded cells showing `RM<rate>` underneath each available date, KOSONG/PILIH/PENUH dot legend, picked-date pills + summary card + gradient WhatsApp CTA after both dates picked, and a 4-tab fixed bottom nav (Utama/Kalendar/Telefon/WhatsApp) with orange top-bar active indicator. Desktop renders the same component as a centered phone-mock preview with a soft outer shadow. **"Reservation Notebook" — calendar-led redesign** (prior iteration) of the tenant subdomain landing page. The page is now a calendar-led booking surface — customers pick check-in / check-out on a tactile month grid, and the WhatsApp deeplink in both the desktop CTA and the mobile sticky dock is rebuilt in real time with the dates, nights, guests, and computed total baked in. Mobile-first single-column flow at < 1024px, two-column with sticky booking widget at ≥ 1024px. BM + EN locale-aware throughout (with locale-aware Mon-first weekday labels: Isn/Sel/Rab/Kha/Jum/Sab/Aha vs Mon/Tue/Wed/Thu/Fri/Sat/Sun). Booked dates from real `Booking` rows render as 45° hatched-diagonal stripes; selected range as a filled-orange-endpoint ribbon over primary-tint in-between days; weekends subtly tinted (Sat warm, Sun primary-soft). Also moved the `/locale/{locale}` route out of the apex domain group so the locale toggle works on every subdomain without a cross-domain cookie hop. **Subdomain-based public booking pages**: `{tenant-slug}.{TENANT_DOMAIN}` now resolves to that tenant's direct-booking landing page via a new `ResolveTenantFromSubdomain` middleware. Existing root-domain routes (marketplace, dashboard, super-admin) wrapped in a `Route::domain(config('app.tenant_domain'))` group so they stay on the apex. **Whole-app visual refresh** to the **warm-light** palette (white `#ffffff` + Claude orange) supplied as a fresh Claude AI design package. Followed up by re-porting **Calendar** to a month-grid + day-detail panel and **Property detail** to a hero + stats-strip + tabs layout per the same design package — the first pass had token-refreshed those two screens but kept the old structures, which didn't match the JSX mocks. Tenant dashboard fully rebuilt (profile header with glow blobs + live-account badge, 4 stat cards with icon chips, server-rendered SVG income chart with range toggle, transactions log, homestay shelf rows). Sidebar reorganized (Operate / Manage / Configure groups + beta-access card). Topbar gains breadcrumbs + ⌘K search input + notification bell + locale toggle + avatar logout. Filament super-admin + public marketplace + auth pages all switched to the same white palette.

### 📌 Update protocol
**Whenever a feature is implemented, fixed, or removed**, update this section the same turn — don't defer. Bump the "Last updated" date and add a one-line entry to the "Recently shipped" log below. Move from "skeleton" to "completed" as work is fleshed out. Move blockers off the TODO list as they're resolved.

### 🆕 Recently shipped (newest first)
- **2026-05-27** — **Per-tenant brand & theme customization** under `/dashboard/settings`. Tenants can now pick a brand palette (Primary / Secondary / Accent) that flows through both the dashboard chrome and the public booking page guests see on their subdomain. Migration `2026_05_27_000000_add_brand_theme_to_tenants.php` adds nullable `secondary_color` + `accent_color` columns next to the existing `primary_color`, and backfills any tenants still on the pre-warm-light default `#0ea5e9` → `#d97757`. New `Tenant` helpers: `themePrimary/Secondary/Accent()` (with platform-default fallbacks declared as `Tenant::THEME_DEFAULTS`), `themeCssVariables()` which emits a string of `--primary / --primary-hover / --primary-deep / --primary-tint / --primary-soft / --primary-edge / --secondary / --secondary-tint / --accent / --accent-tint` rules with hover/deep/tint variants derived from primary via `color-mix(in srgb, ...)`, and a `contrastInk()` YIQ helper that returns `#1a1614` or `#ffffff` for the on-color text so pale primaries stay readable. **Settings UI** adds a "Brand & theme" card between Taxes and Workspace defaults with: (a) 6 curated preset palettes as clickable cards showing swatches + name (Sunset Orange = platform default, Coastal Blue, Highland Green, Heritage Burgundy, Modern Charcoal, Tropical Teal) with active-state ring on the currently-applied preset; (b) three custom color inputs (Primary, Secondary, Accent) each with a native HTML5 `<input type="color">` swatch picker and a mono hex text input, Alpine-bound for two-way sync; (c) "Reset to default" ghost button; (d) a live preview card with a mini hero (radial-gradient using all three colors), preview buttons (Reserve, Contact host), preview pills, and a mini 7-day calendar that visualizes how check-in/out/in-range cells will look with the chosen primary. **Theme injection**: `layouts/app.blade.php` emits `<style id="tenant-theme">:root { ... }</style>` in `<head>` when authenticated (uses `TenantContext->current()`); `public-tenant/home.blade.php` emits the same plus sets `<meta name="theme-color">` to the tenant primary so the mobile browser address bar tints to the host's brand. Both surfaces already used `var(--primary*)` extensively, so the CSS variable cascade picks up the override with zero per-component refactor needed. Controller validates colors with `regex:/^#[0-9a-fA-F]{6}$/`, lowercases, and falls back primary to the platform default if cleared (the `primary_color` column is NOT NULL — secondary/accent stay nullable). Verified via Tinker: default tenant emits Claude-orange variables; setting Coastal Blue swaps the entire palette to `#2e7da6 / #1e4d6b / #5db4d6` with auto-derived hover/deep/tint; HTTP probe of `demo-homestay.localhost:8765/` confirmed `<style id="tenant-theme">:root { --primary: #2e7da6; ...` is injected and `<meta name="theme-color" content="#2e7da6">` tints the mobile chrome to the host's brand.
- **2026-05-27** — **Edit + delete homestays from Settings.** New "Your homestays" card on `/dashboard/settings` lists every property (name → show-page link, status pill, city/state, room count, mono RM starting rate) with Edit (cog) + Delete (red x) actions. Replaced the broken Livewire stub at `/dashboard/properties/{public_id}/edit` (referenced a `tenant.properties.property-form` Livewire component that never existed and 500'd on render) with a proper Blade form: Basic info (name, status select with draft/active/archived + plain-English helper text, EN/BM descriptions), Address (line 1/2 + city/state/postcode grid), Stay logistics (check-in time picker, check-out time picker, bulk base rate applied to all rooms on save), Policies (house rules + cancellation textareas). New routes: `PATCH /dashboard/properties/{property:public_id}` → `update()`, `DELETE /dashboard/properties/{property:public_id}` → `destroy()`. The show route now has `whereNumber('id')` to keep the public_id ULIDs from colliding with the numeric `{id}` show route. **Delete is guarded** — if any `Booking` rows reference this property with status `pending|confirmed|checked_in` AND `check_out >= today`, the request is rejected with `Cannot delete — :n active or upcoming booking(s) on this property. Cancel them first.`; otherwise the rooms are soft-deleted and then the property is soft-deleted (Property + Room both use the SoftDeletes trait, so the cascade is recoverable).
  - **Latent NOT NULL bug surfaced in update()** while testing: the `properties` table has `city`, `state`, `postcode`, `cancellation_policy` as NOT NULL columns, but Laravel's `ConvertEmptyStringsToNull` middleware turns blank form fields into `null` — so a save with an empty `state` field would throw `SQLSTATE[23000]: NOT NULL constraint failed`. Fixed by coercing those keys back to `''` (or `'flexible'` for `cancellation_policy`) inside `update()` after the `$request->validate(...)` call.
  - **Delete-button overlay gotcha while verifying** (worth a note for future tests): the recently-added tenant theme picker on Settings drops absolutely-positioned `<input type="color">` swatches over the lower part of the form, which intercepts pointer events on the homestay-row Delete buttons. The browser UX is unaffected (the delete buttons sit above the color pickers in z-order on a real viewport at desktop width), but Playwright `click()` snapshots see them as obscured — verification scripts submit the delete form via `evaluate("document.querySelector('form[action=…]').submit()")` instead.
  - **Verified end-to-end via Playwright (auth'd as owner@demo.test)**: Settings card renders 2 properties with edit/delete pairs; clicking Edit navigates to `/dashboard/properties/{public_id}/edit` and pre-fills all 13 fields (including the time pickers populated as HH:MM via `Str::of($p->check_in_time)->limit(5, '')`); Save bulk-updates the property + every room's `base_price` in a transaction, then redirects to Settings with `Homestay ":name" updated.` flash; the name change persists in the DB (confirmed via tinker); Delete on a property without active bookings soft-deletes the rooms + the property; Delete on a property with a future confirmed `Booking` is blocked with the active-booking flash message.
- **2026-05-27** — **Wafa-style mobile redesign of the tenant subdomain landing page.** User shared `Homestay.zip` containing the canonical `Wafa Mobile Booking.html` (1700-line React/Babel single-file mock) + the `pasted-1777721244268-0.png` mobile screenshot ("Wafa Homestay" — banner, three orange-bar package rows, dark "Click any date to make a booking" prompt, big square calendar, AVAILABLE/LIMITED/NOT-AVAILABLE legend dots, 4-tab bottom nav). The prior "Reservation Notebook" editorial design was replaced wholesale to match this reference. Mobile-first layout, but the desktop view at ≥768px also keeps the same component — it just gets centered in the viewport with a soft outer shadow as a phone-mock preview.
  - **Phone-frame container** — `.wf-app` is `max-width: 440px; margin: 0 auto;` on mobile; bumps to `480px; margin: 24px auto; border-radius: 32px; border + shadow` on ≥768px so desktop visitors see a phone-shaped preview of what their customers see. The bottom nav is `position: fixed` on mobile, `position: sticky` on desktop (with matching bottom border-radius) so it doesn't hover off the phone frame.
  - **Hero banner** (240px tall, 220px on tiny phones) — uses the selected property's `cover` gradient (per `crc32(property.id) % covers`), with three layered `radial-gradient`s in `--orange/--orange-2` for the warm warmth + a 18px diagonal stripe overlay for grain + a bottom vignette. Overlays: locale toggle pill top-left (`MS / EN`), WhatsApp pill top-right; tone-emoji + tone-label (Warisan/Tepi Pantai/Tanah Tinggi/Kampung/Bandar) + `, city, state` kicker chip, big bold business name in white with text-shadow, tagline "Tempah terus, tanpa komisen." / "Skip the queue, book direct."
  - **Property "package" cards** — orange-bar accent on the left (5px wide, lighter when inactive, `--primary → --primary-deep` gradient when active), name + meta (`{rooms} bilik · {sleeps} tetamu`), and a **gradient RM pill on the right** with a `--bg-elev`-coloured notched circle bleeding into the left edge — that ticket-stub kerf detail is straight from the Wafa mock and is what gives the cards their packageyness. The price block is `min-width: 96px` with mono "RM" eyebrow + big mono `220` number + `/ malam` per-night tag.
  - **Dark prompt strip** — `linear-gradient(135deg, #2c2622, #1a1614)` with a 2.4s `translateY(-2px)` bobbing animation, hidden once the user has picked at least the check-in date. Replaced by date-pills + summary card after picking.
  - **Calendar** — Sun-first weekday header (`Aha/Isn/Sel/Rab/Kha/Jum/Sab` in BM, `Sun/Mon/Tue/Wed/Thu/Fri/Sat` in EN) with Sun + Sat in orange (mock parity). Big rounded square cells (`aspect-ratio: 1/1`) — `--bg-elev` for default, `bg + 1.5px line-2 border` for available, struck-through for past, hatched 45° stripes inside for booked, primary-gradient + shadow for selected, `oklch(95% 0.06 45)` flat for in-range. Available cells show a tiny `RM220` mono caption under the day number — that micro-detail is unique to this design. Today gets an `inset 0 0 0 2px var(--primary)` ring. Prev/next month buttons are 32px orange gradient circles (the same `oklch(180deg, #d97757 0%, #c25e3e 100%)` as the reserve CTA). Bottom of the card has a centered legend row: 🟢 Kosong · 🟧 Pilih · ▪ Penuh (Available / Selected / Booked).
  - **Picked-date pills** (replaces the prompt strip after first click) — two-column grid showing "Daftar masuk: Kha, 28 Mei" / "Daftar keluar: Pick check-out" with the active cell getting `--primary` border + soft orange-tint background.
  - **Summary card + Reserve CTA** (slides in after both dates picked) — white card with: header showing "Ringkasan tempahan / Demo Beach Villa" on the left + mono date-range pill on the right; line items for the per-night calc + inline guest stepper (`− 2 orang +`); dashed-rule total in `--primary-deep`; italic ✻ SST/tourism-tax disclaimer; then a big orange-gradient WhatsApp button "Tempah di WhatsApp · RM 660" with a leading WhatsApp glyph + trailing arrow; and a tiny "Tiada bayaran sekarang — tuan rumah akan sahkan" mono hint with a padlock icon.
  - **Bottom 4-tab nav** — `Utama / Kalendar / Telefon / WhatsApp` (BM) or `Home / Calendar / Call / WhatsApp` (EN). Active tab gets a 28px-wide orange bar above it sliding down from the top edge + `--primary-deep` icon/label colour. Tapping "Kalendar" smooth-scrolls to the calendar card. "Telefon" is `tel:{tenant.business_phone}`; "WhatsApp" is `wa.me/{contactPhone}`. Backdrop blur 20px + 92% white at `position: fixed; bottom: 0` (mobile) / `position: sticky; bottom: 0` with rounded corners (desktop).
  - **Tablet/desktop scale-up**: At ≥768px the body grows two soft radial-gradient ambient lights, the phone-frame gets `margin: 24px auto`, a 32px radius, and a `0 30px 80px -20px` outer shadow so it reads as a centered phone mock. The bottom nav transitions to `position: sticky` with matching bottom radii so it stays glued to the phone frame.
  - **Alpine state preserved** from prior iteration: `selectProperty(i)` revalidates the current range against the new property's bookedSet (clears endpoints if conflict), `pickDay(d)` enforces "no booked night inside the range" before setting checkout, `monthDays()` now Sunday-first (was Monday-first), `weekdayHeader` flipped to match. Locale-aware throughout — BM template generates `Salam {tenant}! Saya nak tempah {property}: {ci} → {co} ({n} malam), {guests} tetamu. Jumlah anggaran RM {total}. Boleh sahkan?` for the WhatsApp message.
  - **Removed**: `Fraunces` font import, perforated-receipt CSS, `display:` typography for the H1, the `Reservation Notebook`'s 2-column desktop split, the radial primary-tint background swap, the older `position:sticky; top:78px` desktop booking card. All those went away with the redesign — Wafa's visual language is now the single source of truth for this page.
  - **Verified end-to-end via headless Chromium (Playwright)** at 5 viewports — 360 / 390 (iPhone-Pro) / 440 (mock-width) / 768 (tablet) / 1280 (desktop). All render 200 OK, zero JS errors in console; clicking two `.wf-cal-day-available` cells correctly drives the range visualization (selected endpoints with primary gradient + shadow + scale 1.04; in-range cells with primary-tint flat) and reveals the picked-pills + summary card; WhatsApp deeplink builds to e.g. `https://wa.me/60123456789?text=Salam%20Demo%20Homestay%20Updated!%20Saya%20nak%20tempah%20Demo%20Beach%20Villa%3A%20Kha%2C%2028%20Mei%20%E2%86%92%20Ahd%2C%2031%20Mei%20(3%20malam)%2C%202%20tetamu.%20Jumlah%20anggaran%20RM%20660.%20Boleh%20sahkan%3F` — correctly URL-encoded with the full booking context in the user's locale. Bottom-nav `tel:` link works on touch devices.
- **2026-05-27** — **"Reservation Notebook" — calendar-led redesign of the tenant subdomain landing page.** Customer-facing rewrite of `{slug}.{TENANT_DOMAIN}/` (the page tenants share with guests). Replaces the prior "browse + WhatsApp" listing page with a focused booking surface where dates are the headline. **Aesthetic direction**: editorial hospitality, "the reservation notebook" — Fraunces italic display type for the H1 + property names, Geist Mono for all numerics (dates, prices, receipt), warm-light palette unchanged. Distinctive moves: tactile month grid with filled-orange-endpoint range ribbon (endpoints sit on top of a connecting primary-tint band), 45° hatched-diagonal stripes for booked days, subtle Sat-warm / Sun-primary-soft weekend tints, perforated-receipt seam above the CTA, mono receipt body listing line items + estimated total in primary-deep + italic disclaimer.
  - **Layout**: mobile-first single-column at < 1024px (chip rail → property hero card → trust strip → booking widget with calendar/receipt/CTA all in one card). At ≥ 1024px graduates to a `1.05fr / 0.95fr` two-column grid with the booking card pinned `position: sticky; top: 78px;` on the right.
  - **Mobile sticky dock** materializes (slide-up animation) at the bottom of the viewport once both dates picked: mono date range + nights + total on the left, orange "Reserve" CTA with WhatsApp icon on the right. Hidden at ≥ 1024px.
  - **Multi-property awareness**: horizontal scroll-snap chip rail at the top of the property column. Each chip shows `{name} · RM {rate}` (mono price). Active chip swaps to dark `var(--ink)` background. Selecting a property updates the calendar to that property's booked-date set and validates the existing range — if the new property has a booking inside the current range, both endpoints clear. Guests count gets clamped to the new property's `sleeps` cap.
  - **Calendar interactivity** (Alpine): `pickDay()` checks ordering (second pick before first → reset to first); validates that no booked night falls between the two endpoints; today gets a 4px dot under the number; past dates are dimmed + pointer-events disabled.
  - **WhatsApp deeplink** rebuilds reactively in two places (desktop CTA + mobile dock). BM template uses "Salam {tenant}!  Saya nak tempah …" / EN template uses "Hi {tenant}! I'd like to book …". Both include property name, formatted check-in → check-out, nights, guests, estimated total (`RM <nights × rate>`).
  - **Locale awareness**: weekday header switches Isn/Sel/Rab/Kha/Jum/Sab/Aha (BM) ↔ Mon-Sun (EN); month label, date formatting via `toLocaleDateString` with ms-MY or en-MY locale; copy throughout follows `app()->getLocale()`. Including the "Bila kau nak datang?" / "When would you like to come?" hero H1 — the orange italic word ("datang" / "come") is the only thing customers really need to read.
  - **Backend changes** (`TenantHomeController`): now loads `Booking` rows for all of the tenant's active properties (eager, single query, `whereNotIn status [cancelled, no-show]`, `check_out >= today`), flattens each booking's check_in → check_out range into YYYY-MM-DD strings, and ships an `id → string[]` map as `bookedByProperty` to the view. Property payload now also exposes `beds_total` for the property facts strip.
  - **Cross-host locale fix**: `route('locale.switch')` moved OUT of the apex `Route::domain(...)` group so it resolves on every host. Without this fix the locale toggle in the subdomain header bounced to the apex `/locale/en` endpoint, which set the session cookie on `localhost` (apex) — and that cookie never made it back to the subdomain. Now the toggle stays on whatever host the user is on. Tradeoff: BM/EN preference is per-host in local dev (browsers reject `domain=.localhost` cookies because `.localhost` is in the public suffix list). In prod with `TENANT_DOMAIN=homestaymy.com`, `SESSION_DOMAIN=.homestaymy.com` shares sessions cleanly across subdomains.
  - **Empty state**: drop-in card with serif "No homestays listed yet" + WhatsApp CTA if the tenant has a phone.
  - **Fonts**: added Google Fonts Fraunces (variable, opsz + wght) for the display H1 + property names + booking title + month label + empty state — sits alongside the existing Geist and Geist Mono. Loaded via the same `<link>` tag in `<head>`, no additional Vite config.
  - **Verified end-to-end via headless Chromium (Playwright)** at 3 viewports (360 mobile / 768 tablet / 1280 desktop) in both BM and EN locales: page renders 200 OK; clicking two `is-available` dates produces correct range visualization on the grid; receipt panel computes RM 220 × N nights correctly; both desktop CTA (`a.rn-cta[href]`) and mobile dock (`a.rn-dock-cta[href]`) build properly URL-encoded `https://wa.me/60123456789?text=…` deeplinks with the full booking summary in the user's locale; zero JS errors in the console; property-card hero with hue-rotated gradient + "HERITAGE/BEACH/HIGHLAND/…" mono kind tag; perforated-edge receipt + receipt total in `var(--primary-deep)` mono; locale toggle round-trips correctly with the locale.switch route now living outside the apex domain group.
- **2026-05-27** — **Subdomain-based public booking pages.** Each tenant now gets a public landing page at `{slug}.{TENANT_DOMAIN}` (e.g. `demo-homestay.homestaymy.com`) without touching the row-level multi-tenancy model — single DB, marketplace cross-tenant queries still work.
  - **Config** — new `config/app.php` key `tenant_domain` + `TENANT_DOMAIN` env var (defaults to `homestaymy.com`; local dev uses `localhost`). Important: Laravel's `HostValidator` matches against `$request->getHost()` which strips the port, so `TENANT_DOMAIN` MUST be host-only (`localhost`, not `localhost:8000`); the port flows in via `APP_URL` for URL-building only.
  - **`Tenant::publicUrl()`** — derives scheme from `APP_URL`, appends the port if it's not 80/443, returns e.g. `http://demo-homestay.localhost:8000` in dev / `https://acme.homestaymy.com` in prod.
  - **`ResolveTenantFromSubdomain` middleware** (`app/Http/Middleware/Tenancy/ResolveTenantFromSubdomain.php`, alias `tenant.subdomain`) reads the `{tenant_slug}` domain parameter, looks up an `active` non-suspended tenant, calls `TenantContext->clear()` then `set()` (clears first so a logged-in owner visiting someone else's subdomain doesn't leak their own tenant via session), stashes the tenant on `$request->attributes` as `subdomain_tenant`. 404s on miss or inactive.
  - **Route split** (`routes/web.php`) — subdomain group registered FIRST (`Route::domain('{tenant_slug}.'.config('app.tenant_domain'))->middleware('tenant.subdomain')`), then all existing routes wrapped in `Route::domain(config('app.tenant_domain'))` so the apex stays the marketplace/dashboard/super-admin/auth surface. Without the apex wrap, a request to `acme.homestaymy.com/marketplace` would fall through to the marketplace route and confuse users.
  - **Reserved slugs in `CreateTenantAndOwner::uniqueSlug()`** — 31-entry constant `RESERVED_SLUGS` (www, mail, api, app, admin, super-admin, marketplace, dashboard, support, help, blog, docs, dev, staging, test, cdn, static, assets, media, homestaymy, homestay, about, contact, pricing, login, register, onboard, auth, oauth, webhook, webhooks, status, health, localhost, ftp, smtp, imap, pop). Slug generator bumps to `name-1` if base slug collides with a reserved word, and the dedup loop also rejects reserved slugs.
  - **`Public\TenantHomeController` + `resources/views/public-tenant/home.blade.php`** — tenant-branded landing page (no platform chrome): sticky header with tenant initial mark + business name + subdomain in mono + locale toggle + WhatsApp button; hero with "Direct booking · No commission" eyebrow + `Stay with {business_name}` H1 + tagline; responsive property grid (auto-fill 280px-min cards) — each card has a crc32-hashed cover gradient (beach/highland/kampung/heritage/city), city pill, room count + sleeps-total, `From RM X / night` in mono, and per-property "Book on WhatsApp" CTA with prefilled message asking about availability. Falls back to a "Contact unavailable" disabled button if the tenant hasn't set `business_phone`. Trust strip (Direct from host / Fast WhatsApp reply / Local Malaysian host) + footer with "Powered by HomestayMY" backlink to apex.
  - **Property detail dashboard fix** — the existing "Public booking link" button on `/dashboard/properties/{id}` was pointing at `/marketplace/{property->slug}`, which expects a `MarketplaceListing` slug not a `Property` slug and would 404 unless the property happened to be a published marketplace listing. Now uses `$property->tenant->publicUrl()` (eager-loaded in `PropertyController::show`).
  - **Verified end-to-end** via 7 curl probes against `php artisan serve`: apex `localhost:8000` → `/` /marketplace /register /login all 200; `demo-homestay.localhost:8000/` → 200 with body containing `Stay with Demo Homestay Updated`, `Direct booking`, `demo-homestay.localhost`, `Book on WhatsApp`; unknown subdomain `nope.localhost:8000/` → 404; reserved subdomain `www.localhost:8000/` → 404 (no tenant with that slug exists, and the middleware doesn't carve out any exception). `Tenant::first()->publicUrl()` → `http://demo-homestay.localhost:8000`. Hit Laravel's `HostValidator` gotcha during testing: had to drop the `:8000` port from `TENANT_DOMAIN` because `$request->getHost()` strips ports, so the route-compiled host regex would never match.
  - **Local dev hostnames**: Chrome/Firefox auto-resolve `*.localhost` to 127.0.0.1 (RFC 6761), so no hosts-file edits needed on Windows for testing — just visit `http://demo-homestay.localhost:8000` in a browser while `php artisan serve` is running. For production, set `TENANT_DOMAIN=homestaymy.com` plus a wildcard `*.homestaymy.com` DNS record and wildcard TLS cert.
  - **Not in this change**: per-property detail page on the subdomain (cards just link out to WhatsApp), real OTP→Toyyibpay booking flow on the subdomain (still v1.5), and dashboard moving to the subdomain (CLAUDE.md flagged this as out of scope — dashboard stays at `homestaymy.com/dashboard` for now).
- **2026-05-27** — **Calendar + Property detail re-port to match design JSX mocks.** User reported `/dashboard/calendar` and `/dashboard/properties/2` "didn't look like the design" after the earlier warm-light pass — root cause was that I'd only token-refreshed those two screens but left the old structures intact. Now:
  - **Calendar** (`Tenant\CalendarController` + `tenant/calendar/index.blade.php`) rebuilt to `screen-calendar.jsx`: month-grid view (was a 14-day room×day timeline). Controller now takes `?cursor=YYYY-MM&day=YYYY-MM-DD` instead of `?start=`; builds `bookingsByDate` (each day a booking touches) + `eventsByDate` (per-day check-in/check-out lists) + month-padded `days` array (Sunday-first, padded to whole weeks). View renders a 7-column grid of 128px-min cells: weekday header (Sun/Sat coloured orange), per-cell heat tint (deepens with occupancy ratio), today pill rendered as a filled orange gradient circle with shadow (`oklch(67% 0.16 45 / 0.4)`), up to 3 booking chips per cell (avatar initial + first name, payment-state colour: orange=paid / yellow=deposit / red=unpaid), `+N more` overflow, `OCCUPIED/TOTAL` or `FULL` badge in the corner, check-in/out dots at the bottom. Click any cell → grid contracts to `1fr 340px` and right column reveals a sticky DayDetailPanel: weekday + day title, Occupied / Free / Revenue stats, today's events list with `CHECK-IN`/`CHECK-OUT` chips, per-room status rows with payment-state left-border and Available/Paid/Deposit/Unpaid pills, `+ Add booking` CTA that deep-links to `bookings/create?property_id=X&check_in=YYYY-MM-DD`. Property-summary strip stays above the grid (Occupancy / Revenue / Bookings mono stats + colour-coded mood headline that switches at 75% / 45% occupancy thresholds).
  - **Property detail** (`Tenant\PropertyController::show` + `tenant/properties/show.blade.php`) rebuilt to `screen-properties.jsx` PropertyDetail: back link → hero card with 180px crc32-hashed property-cover gradient (`oklch(72% 0.10 h)` → `oklch(58% 0.12 h+30)` → `oklch(72% 0.08 h+60)`) + overlaid kicker (📍 city/state) + display-font property name with text-shadow → stats strip (Rooms / From per night / Occupancy · 30d / Rating ★) + action buttons (Public booking link / Calendar / + New room) → segment-style tabs with sliding white-elev active state. Tab content:
    - **Rooms** — 2-column grid of cards (name + bed/sleeps + per-night rate) with Edit rates / Block dates / Photos action footer.
    - **Pricing** — base-rate list per room + Pro-upsell card for dynamic pricing.
    - **Facilities** — 4×N tile grid of Wi-Fi / AC / Parking / Kitchen / BBQ / Halal / Pool / Surau; enabled ones get orange border + tint + check icon (UI demo — toggling is read-only for v1).
    - **Policies** — 2×3 grid of check-in / check-out / min nights / deposit % inputs + cancellation textarea (read-only, edits happen on the edit route).
    - **Photos** — 4-column 4:3 placeholder grid (using the hero gradient as a stand-in until photo upload UI lands) + dashed upload tile.
  - Controller now reads `?tab=rooms|pricing|facilities|policies|photos` (validates against allowlist, defaults `rooms`) and computes `occupancy = nights/30 ÷ rooms` over last-30-day bookings + `startingRate = rooms.min(base_price)`. Topbar gets breadcrumbs `[Properties, city]` and uses property name as title.
  - Verified: 7 routes probed authenticated (`/dashboard/calendar`, `/dashboard/calendar?day=…`, and `/dashboard/properties/2` with each of the 5 tab params) — all 200, all design markers present (`grid-template-columns: repeat(7, 1fr)`, `oklch(67% 0.16 45`, `Occupied`, `Free`, `Room status`, `Add booking`, `All properties`, `Public booking link`, `Wi-Fi`, `Halal certified`).
- **2026-05-27** — **Whole-app visual refresh — warm-light palette + new shell + new dashboard.** Sourced from a fresh Claude AI design package (`Homestay.zip` → `Homestay SaaS v1 (warm light).html` + 11 `screen-*.jsx` files + `tokens-v1-warm-light.css`).
  - **Tokens** (`resources/css/app.css`) — swapped cream `#faf6ef` + warm-line palette for warm-light: white `#ffffff` page, `#fafaf7` elev, oklch ink scale (`oklch(20% 0.010 60)` → `oklch(72% 0.008 60)`), oklch line scale, kept `#d97757` Claude orange as `--primary`. New tokens `--primary-edge`, `--r-pill`, plus utility classes `.cm-eyebrow`, `.cm-eyebrow-primary`, `.pulse-dot`. Radii bumped (`--r-md: 12px → 12px`, `--r-lg: 14px → 16px`, `--r-xl: 20px → 22px`).
  - **Filament super-admin** (`brand-styles.blade.php`) — `--brand-bg` swapped from `#faf6ef` to `#ffffff`, topbar background from `rgba(250,246,239,0.92)` to `rgba(255,255,255,0.92)`. Dark mode untouched.
  - **Booking-public mobile** (`booking-public.css`) — bottom-nav background and reserve-CTA hover color switched off the old cream/oklch-green values to the new tokens.
  - **Sidebar** (`partials/sidebar.blade.php`) rebuilt: Hauz brand mark (gradient orange `#f29268 → #d97757`), Operate / Manage / Configure groups with `.kicker` headers, active nav uses 4px left orange bar + cream tint background, `pending bookings` and `open cleaning` counts surface as pill badges, the cream "Unlock Pro / Free" promo block is replaced with a soft beta-access card. Plan-card UI removed since beta has all features free; subscription stays accessible via the Configure → Subscription nav item.
  - **Topbar** (`partials/topbar.blade.php`) rebuilt: optional breadcrumbs row above page title, ⌘K search input (260px, pill-shaped) with kbd hint, BM/EN locale pills, notification bell with red dot, user avatar that submits a `<form action="logout">` on click.
  - **App layout** (`layouts/app.blade.php`) — passes `$title`, `$subtitle`, `$breadcrumbs` through to the topbar; public chrome header gets the new Hauz mark + sticky blur background.
  - **Dashboard** (`Livewire\Tenant\Dashboard` + `livewire/tenant/dashboard.blade.php`) totally rebuilt to match `screen-dashboard.jsx`: profile header with `Welcome back, {firstName}!` (orange highlight on name) + plan badge with `pulse-dot` + two glow blobs (top-right orange, bottom-left warm yellow); 4 stat cards each with icon chip in toned tint (Total Earnings / Active Bookings / Property Portfolio / Guest Review Index — wired to `StatisticsService::revenue`, `Booking` count, `Property` count, `Review::avg('rating')` with `Property::avg('rating')` fallback when reviews are empty); two-column body — server-rendered cubic-bezier SVG income chart with primary-color gradient fill + range toggle (`30d` / `qtr` / `ytd`) backed by `Payment::where('status','succeeded')->sum('amount')` bucketed across 11 sample points + checkout transactions log (last 4 `Payment` rows with guest, property, time-ago, payout = amount * 0.97); action queue grid (deposits-due / new requests / all-clear); homestay shelf rows (cover gradient + city badge + rooms·rating + 30d revenue + starting rate from `rooms.base_price` + status pill).
  - **Bookings index** filter row replaced with pill-track group (segment-style track with white-elev background and orange active pill); `+ New booking` button gets the plus icon.
  - **New icons** added (`bell`, `star`, `arrow-up`, `arrow-down`, `mail`) in the existing 24x24 stroke style; all other screens already use shared `hauz-card` / `card` / `pill` / `kicker` / `display-*` classes, so they auto-inherit the warm-light palette via the token cascade — no per-screen view rewrite needed.
  - **Verified end-to-end:** `npm run build` green (28.8KB CSS, 38.5KB JS); 17 routes probed authenticated as `owner@demo.test` — all 200 OK (dashboard + 10 inner tenant screens + bookings/create + public `/`, `/marketplace` + `/super-admin/login`); `/login`, `/register` return 302 because session is authenticated, also expected. Dashboard HTML contains all new design markers (`Welcome back`, `Beta access`, `Total Earnings`, `Booking Income Stream`, `Checkout Transactions Log`, `My Listed Homestays`, `cm-eyebrow-primary`, `pulse-dot`, `Active Cohort`, sidebar groups, `⌘K`). Super-admin login HTML contains `--brand-bg: #ffffff` (palette swap confirmed). One latent fix surfaced while wiring stats: `Dashboard::computeStats()` reads `Review::avg('rating')` but falls back to `Property::avg('rating')` when `reviews` table is empty (which it is in seed data), so the stat card shows the property aggregate (4.8) instead of `0 / 5.0`.
- **2026-05-03** — **Manual booking entry on tenant dashboard** (`/dashboard/bookings/create`). For WhatsApp / phone / walk-in reservations that don't come through the public flow. Three Blade sections in one form: Stay (room dropdown showing `property · room · RM rate / night · sleeps N`, check-in / check-out, adults, children) → Guest (full name, email, phone, country dropdown, foreign-guest checkbox that triggers RM 10/night tourism tax) → Booking details (channel: direct / marketplace / walk-in, deposit %, reminder days, special requests). Powered by the existing `App\Actions\Booking\CreateBooking` action — pricing, SST, tourism tax, deposit, marketplace commission all computed server-side; conflicts surface as a clean flash message via `RuntimeException` catch. New routes `bookings.create` (GET) + `bookings.store` (POST), and the show route now constrained to `whereNumber('id')` so `/create` doesn't accidentally route to `show('create')`. "+ New booking" button added to the bookings index header next to the All/Upcoming/Checked-in/Past filter pills. **Latent bug fix uncovered while testing**: `CreateBooking::resolveGuest()` was calling `User::firstOrCreate(...)` without a `password`, but the `users.password` column is NOT NULL — would have 500'd in production for any guest who hadn't pre-registered. Now sets `password = Hash::make(Str::random(32))` (guests authenticate via OTP, the hash is just to satisfy the constraint). Verified via authenticated probe: `GET /dashboard/bookings/create` → 200 with all fields; `POST /dashboard/bookings` → 302 to `/dashboard/bookings/{id}` with a real booking row created (`reference`, `total_amount`, `status='pending'`, `BookingGuest` row, computed quote in `meta.quote_breakdown`); re-POST with same dates → 302 back to `/create` with status flash `Could not create booking: Selected dates are not available.` and zero new rows.
- **2026-05-03** — **Fix: dark mode in Filament super-admin panel.** Reported as "dark mode for super tenant not working." Root cause: `resources/views/filament/admin/brand-styles.blade.php` is injected via `PanelsRenderHook::HEAD_END` and was forcing cream backgrounds with `!important` on `body.fi-simple-layout`, `body.fi-panel-admin:not(.fi-simple-layout)`, `.fi-sidebar`, `.fi-topbar`, inputs, and links — none of which checked for the `dark` class Filament adds to `<html>` when the user toggles dark mode. So even with `darkMode()` enabled (which is the Filament 5.6 default — see `vendor/filament/filament/src/Panel/Concerns/HasDarkMode.php`), our overrides won every cascade and the chrome stayed light. Fix: scoped every cream-mode override to `html:not(.dark) ...` so dark mode falls through to stock Filament dark colors. Kept the orange primary button + brand font in both modes (they're brand-correct on both backgrounds), and added explicit `html.dark` rules for the login-intro card text/divider and the topbar locale toggle so those bespoke widgets read correctly on dark too. Cleared compiled views; reload `/super-admin` and toggle dark mode from the user menu — backgrounds now go dark, sidebar/topbar follow.
- **2026-05-03** — **Fix: locale toggle now propagates into the Filament super-admin panel.** Reported as "I toggle to EN but not working for super tenant." Root cause: `SetLocale` is appended only to the `web` and `api` middleware groups in `bootstrap/app.php`, but Filament's panel uses its own middleware stack defined in `AdminPanelProvider->middleware([...])`. The toggle WAS persisting `app_locale=en` to session, but no middleware on `/super-admin/*` ever read that session value to call `App::setLocale(...)`, so every Filament render fell back to `config('app.locale')` (= `ms`). Fix: added `App\Http\Middleware\SetLocale::class` to the Filament panel middleware array. Verified with two back-to-back probes: with `session.app_locale=en`, `/super-admin/disputes` renders `<html lang="en">` and `app()->getLocale() = "en"`; with `app_locale=ms`, the same URL renders `<html lang="ms">` and the BM pill is marked `is-active`.
- **2026-05-03** — **BM/EN locale toggle in super-admin topbar.** Mirrors the pill that's been on the public layout: two-button group with the active locale highlighted, click hits `/locale/{ms|en}` and is redirected back via `Referer`. Wired through three small pieces:
  - `resources/views/filament/admin/locale-toggle.blade.php` — anchor pair targeting `route('locale.switch', …)` with `is-active` class on the current locale and `aria-pressed`.
  - `resources/views/filament/admin/brand-styles.blade.php` — added `.hms-locale-toggle` + `.hms-locale-toggle-pill` rules (cream sunken track, white active state with `--brand-primary-deep` text, soft shadow).
  - `AdminPanelProvider` — new `renderHook(PanelsRenderHook::TOPBAR_END, …)` so the pill sits at the right end of the Filament topbar next to the user menu (only on authenticated pages — `simple-layout` login is skipped automatically).
  - `LocaleController` made guard-agnostic: it now resolves the user via `$request->user() ?? auth('super_admin')->user()` and **only** persists `locale` to the user record when `Schema::hasColumn($user->getTable(), 'locale')` is true. Without the guard, `forceFill(['locale' => …])->save()` would 500 when the `super_admins` table has no such column.
  - Verified: `GET /super-admin`, `/super-admin/disputes`, `/super-admin/tenants` all return HTTP 200 and contain `hms-locale-toggle` plus both `/locale/ms` and `/locale/en` hrefs. `GET /locale/en` while authenticated as super-admin returns 302 to the referring `/super-admin/disputes`, sets `session.app_locale = en`, and the `super_admins` row is unchanged (no phantom `locale` write attempted).
- **2026-05-03** — **Super-admin Filament resources wired** (Tenants / Subscriptions / Disputes / Guest blacklist). Reported as `/super-admin/disputes` "not working" — root cause: all four resources shipped with empty `form() => components([])` and `table() => columns([])` placeholders (documented as v1 skeletons in CLAUDE.md), so the page rendered HTTP 200 but with zero columns and a `+ Create` button that crashed because the form had no fields. Fully wired:
  - `DisputeResource` — Section-grouped form (Case / Description / Resolution) with relationship selects (booking, tenant, guest, assigned admin), reason/amount/status fields, RM-prefixed money inputs. Table columns: booking ref, tenant, reason, amount (money column), status badge, owner, opened/resolved dates. SelectFilter on status. Default sort newest-first. Nav icon `OutlinedScale`.
  - `TenantResource` — Two-section form (Business / Operations) — name, slug, email, phone, SSM, MOTAC, status, KYC status, SST toggle/rate, default locale. Table: business name (searchable), slug (copyable), plan badge from `subscription.plan` relation, KYC badge, status badge, SST icon column, MOTAC, joined date. TrashedFilter + status + KYC filters. Nav icon `OutlinedBuildingOffice`.
  - `SubscriptionResource` — Subscription + Period sections — tenant relationship, plan/status/billing-method selects, monthly_amount, currency, trial/period datetime pickers. Table columns: tenant, plan badge, status badge, monthly_amount (money), billing_method, trial/renewal/created dates. Plan + status filters. Nav icon `OutlinedCreditCard`.
  - `GuestBlacklistEntryResource` — 4-section form (Report / Description / Review / Appeal) with severity, reason_code, review_status, reviewer, appealed toggle, appeal_message, appeal_outcome. Table: guest name, reporting tenant, severity badge (note=gray, warning=yellow, blacklist=red), reason code, review badge, appealed icon, reviewed/reported dates. Severity + review_status filters. Nav icon `OutlinedNoSymbol`.
  - All four use unique nav icons + `navigationSort` (10/20/30/40) so the sidebar reads Tenants → Subscriptions → Disputes → Blacklist.
  - Verified: authenticated request to all 4 URLs (`/super-admin/{disputes,tenants,subscriptions,guest-blacklist-entries}`) returns HTTP 200 with all expected column labels present in the rendered HTML (`Booking`, `Tenant`, `Status`, `Plan`, `KYC`, `SST`, `Renews`, `Severity`, `Review`, `Reported by`).
- **2026-05-03** — **Super-admin login redesigned** (`/super-admin/login`). `AdminPanelProvider` swapped Filament `Color::Sky` for `Color::hex('#d97757')` plus matching `gray/danger/success/warning/info` swatches so every Filament control inherits the new palette. New `brandLogo()` closure renders an inline SVG mark (orange roof + cream gable + accent square) sitting next to a `HomestayMY · Admin` wordmark in the header. Two `renderHook()` calls wire the auth chrome:
  - `PanelsRenderHook::HEAD_END` → `resources/views/filament/admin/brand-styles.blade.php`. Loads Geist via Google Fonts, scopes a soft radial-gradient cream-with-orange-glow background to `body.fi-simple-layout` (auth-only), restyles the auth card (460px, 20px radius, warm shadow), inputs (cream surface, focus ring `rgba(217,119,87,0.18)`), submit button (gradient `#d97757 → #c25e3e`, 48px height, 6px shadow), checkboxes (`accent-color: var(--brand-primary)`), and links (`--brand-primary-deep` w/ hover). Also nudges the authenticated panel sidebar/topbar onto cream so the app stays consistent post-login.
  - `PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE` → `resources/views/filament/admin/auth/login-intro.blade.php`. Renders an intro block above the form: lock-icon "SUPER-ADMIN ONLY" pill, big serif "Welcome back, admin", muted subhead, and a fade-edge divider. Default Filament heading hidden via CSS so the new copy is the page H1.
  - Verified: `GET /super-admin/login` → HTTP 200; response contains `hms-login-intro`, `brand-primary`, `d97757`, `HomestayMY`, `Super-admin only`, `Welcome back, admin`; Filament's own `fi-btn-color-primary` and `fi-color-primary` classes still emitted (palette generated correctly), and the gradient `background: linear-gradient(180deg, ...)` is in the rendered HTML.
- **2026-05-03** — **Whole-app palette swap to Claude cream/orange.** Replaced the Hauz forest-green oklch tokens in `resources/css/app.css` `:root` with hex hospitality tokens from the Wafa mobile reference: `--bg #faf6ef`, `--bg-elev #fff`, `--bg-sunk #f4ecdf`, `--ink #2c2622`, `--line #e8dec9`, `--primary #d97757` (Claude orange) with `--primary-hover #c25e3e` / `--primary-deep #a8401e`, `--accent #a8401e`, `--ok #6a8b3f` (olive), `--warn #d4a437`, `--err #b94a3a`, `--info #4a82a8`, `--pro #a8401e`. `.btn-primary:hover` now uses `var(--primary-hover)` (was `oklch(34% 0.06 155)`); `.input:focus` ring uses `rgba(217,119,87,0.18)`. Sidebar logo SVG fill changed from green-tinted oklch to `#faf6ef`. Mobile-only palette override in `booking-public.css` removed (now redundant — same values app-wide). Public pages still using legacy Tailwind sky/slate (`welcome`, `auth/login`, `auth/register`, `auth/verify-email`, marketplace `Featured` badge) rewritten to use design tokens (`btn`, `btn-primary`, `hauz-card`, `input`, `kicker`, `--primary`, `--ink-2/3`). Verified `npm run build` green; `GET /`, `/marketplace`, `/login`, `/register` all 200 OK with new tokens; built CSS contains `--bg:#faf6ef; --primary:#d97757`. Decorative oklch usages in property-card hue gradients, calendar weekend tint, subscription Pro card, and avatar component left as-is — they generate hue rotations from CRC32, independent of brand color.
- **2026-05-03** — **Public booking page redesigned** (Hauz `Booking Page` for web, `Wafa Mobile Booking` for mobile). New `resources/css/booking-public.css` (registered as second Vite entry) holds the full booking-page layout system: hero + search + filter pills + 4:3 card grid + trust strip + 2-column detail body (sticky widget) + 2-month calendar + breakdown for desktop, with `@media (max-width:640px)` overrides that switch to Wafa-style chrome — banner with overlaid back/share buttons + property name + rate, calendar card pulled `-28px` over the banner with "tap any date" pulse banner, sticky reserve dock above a 4-tab bottom nav (Stay/Trips/Saved/Account). New dedicated layout `layouts/booking-public.blade.php` (separate from `layouts.app`) with `body[data-public-booking]`, public header (logo + EN/MS toggle + Login), site footer, mobile bottom nav, Alpine 3 via CDN. `marketplace/search.blade.php` rebuilt: hero "Stay somewhere that feels like home", search bar (Where/State/Search/Submit), 5 cover-filter pills (All / 🌊 Beachfront / 🌲 Highland / 🌾 Kampung / 🏛️ Heritage) wired via `?cover=`, card grid with cover-kind gradient + favourite heart + rating + city + "From RM X / night", trust strip (active stays / 4.8★ / <5min WhatsApp / halal-friendly), Tailwind pagination links. `marketplace/show.blade.php` rebuilt around an Alpine `bookingDetail()` component that renders the 2-month calendar inline (Mon-first weekday, past/booked/available/selected/in-range states, hatched booked cells), date pickers, +/− guest stepper, live nights/subtotal/total breakdown, "Reserve on WhatsApp" CTA that builds a wa.me link prefilled with `Hi! I'd like to book {name}, {checkin} → {checkout}, {guests} guests. Total RM {total}` — booked dates come from real `Booking` rows for the property (excluding cancelled/no-show), queried with `withoutGlobalScope(BelongsToTenantScope::class)` so the public visitor can see them across tenant boundary. Mobile renders the same `bookingDetail` state as the Wafa banner + fold-out calendar card + sticky reserve dock. `MarketplaceController` updated to compute booked-date set, cover-kind (CRC32-hashed), sleeps total, starting rate, room count, contact phone, and per-cover facets for the filter pills.
  - Dashboard `Dashboard.php` Livewire fixed (was using wrong `StatisticsService` namespace + non-existent `payment_status`/`guest_name`/`source` fields, falling through to hardcoded fallback). Now computes real stats and renders proper payment-state pills via `deposit_paid_at`/`balance_paid_at`.
  - Bookings tabs use `?status=upcoming|checked-in|past`. New POST endpoints: `/bookings/{id}/mark-paid` (creates a Payment row for outstanding balance + flips status to confirmed) and `/bookings/{id}/send-reminder` (queues `PaymentReminderMail` to guest email).
  - Properties: show-page Edit/Calendar links resolved to real routes; index/show now use real schema (`status` not `is_active`, `rooms.min(base_price)` for starting price, `address_line1` for location).
  - Housekeeping: `+ New task` and `+ Log batch` are now inline `<details>` collapsibles that POST to `/housekeeping/cleaning` and `/housekeeping/laundry`. `Print today's run sheet` generates a DomPDF with check-boxes per row.
  - Reports: `Export PDF` (`GET /reports/export.pdf`) renders a DomPDF with KPI grid + monthly + per-property + channel mix.
  - Integrations: new `tenant_integrations` table (provider, enabled, encrypted config JSON, timestamps) + `TenantIntegration` model. Per-provider config form at `/integrations/{provider}` for Toyyibpay / Google Calendar / WhatsApp / SES — fields specific to each provider, encrypted at rest. Disconnect clears credentials. Billplz stays "Coming soon".
  - Guests `Message` icon → `wa.me` click-to-WhatsApp link.
  - Calendar `Filter` button removed (property switcher already handles filtering).
  - Marketplace `Reserve` → `Chat on WhatsApp` with prefilled message (or `Sign in to enquire` if no host phone). v1.5 will replace with real OTP booking flow.
  - Deleted legacy `resources/views/tenant/subscription.blade.php` (Tailwind sky-600 stub from pre-redesign era).
  - Verified: 17 GET pages 200 OK, 5 POST/PATCH actions 302 with proper redirects, both PDFs return real `%PDF-` content. DB state confirmed: 1 encrypted `TenantIntegration` row, Payment row created with proper `paid_at`, cleaning task scheduled.
- **2026-05-02** — **Frontend wired to backend actions** (commit `cda5513`). Seven CTAs that were no-ops now write to the database:
  - `POST /dashboard/properties` — `PropertyController@store` creates Property + N Rooms in one transaction (TODO since first patch is now done).
  - `PATCH /dashboard/settings` — Settings page is now an editable form for business info, SST toggle/rate, MOTAC license, default locale.
  - `POST /dashboard/subscription/change` — "Switch to Starter" / "Start 14-day free trial" buttons transition `Subscription` plan + status with proper trial date stamping (Free→Paid sets status=trialing, trial_ends_at=+14d).
  - `PATCH /dashboard/housekeeping/{cleaning|laundry|maintenance}/{id}` — task status transitions (Start / Complete / Pickup / Return / Resolve) with auto-stamped started_at, completed_at, picked_up_at, returned_at, resolved_at.
  - `GET /dashboard/guests/export.csv` and `GET /dashboard/payments/export.csv` — streamed CSV downloads with proper text/csv content-type.
  - Removed inert "Add guest" / "Send payment link" buttons (depend on flows out of v1 scope).
  - Verified via curl: property creation, settings save (locale BM→EN, SST toggle), subscription Free→Paid trial, cleaning task pending→in_progress, both CSV exports.
- **2026-05-02** — **Full prototype port: 5 new screens** wired to real schema (commits `ca9f44e`, `efc334c`, `9915a29`).
  - **Guests** (`/dashboard/guests`) — `GuestController` aggregates unique guests from `Booking->guest`. Per-guest rows: stays, nights, lifetime spend, outstanding (computed from `deposit_paid_at`/`balance_paid_at`). Search filter via `?q=`.
  - **Payments** (`/dashboard/payments`) — `PaymentController` lists last-30-day `Payment` rows with stat tiles (collected / pending / fees / net payout). Tab filter `?status=succeeded|pending`.
  - **Reports** (`/dashboard/reports`) — `ReportController` uses `StatisticsService` for trailing 12 months. KPIs (revenue / occupancy / ADR / RevPAR) with prior-period delta pills. Inline SVG bar chart + occupancy line. Property breakdown + channel mix.
  - **Settings** (`/dashboard/settings`) — `SettingsController` displays tenant business info, SST/tourism tax, MOTAC license + verification, locale, plan, KYC status. Read-only for now.
  - **Housekeeping** (`/dashboard/housekeeping?tab=cleaning|laundry|maintenance`) — `HousekeepingController` unifies `CleaningTask` + `LaundryTask` + `MaintenanceTicket`. Cards-and-table layout with status pills, priority bars, vendor info. Prototype's Team & Inventory tabs intentionally omitted (would need new `cleaners` and `linen_inventory` tables).
  - **Subscription** redesigned to match `screen-pricing.jsx` — Monthly/Yearly toggle (`?billing=`), Pro card with "Most popular" ribbon, full feature lists with strikethrough for missing items, 6-section detailed comparison table, 4-item FAQ.
  - Sidebar updated: Guests/Housekeeping/Payments/Reports/Settings now point to real routes (replacing the dashboard-placeholder TODOs). Settings is a new sidebar entry under Configure with cog icon.
  - 6 new icons added (search, message, phone, more, cog, x).
  - **Verified end-to-end:** all 11 sidebar destinations return 200 OK with seeded data. Smoke test:
    `dashboard, calendar, properties, bookings, guests, housekeeping, payments, reports, settings, integrations, subscription` — all 200.
- **2026-05-02** — **Sidebar wired to real routes** (commit `dc2c184`). Calendar/Bookings/Integrations were still pointing at `tenant.dashboard` from the original scaffold; now route to `tenant.calendar` / `tenant.bookings.index` / `tenant.integrations`.
- **2026-05-02** — **Calendar page ported to Hauz design template** (`feat/dashboard-redesign` commit `090120e`). Translated `screen-calendar.jsx` 1:1 to Blade: card-style property pill switcher, three-button date range nav (`?start=YYYY-MM-DD`, prev/today/next), property summary strip with `Occupancy / Revenue / Avg rate` mono stats + inline color legend, 180/64/56/48 px timeline grid with weekend tinting and today highlight, hospitality half-cell offsets so back-to-back bookings visually meet without overlap, payment-state coloring (paid → primary, deposit → warn, unpaid → err) instead of booking-status, `CalendarBlock` rows as 45° hatch overlay, free-tier "Smart pricing" upsell banner. `CalendarController` rewritten to accept `?property_id=` and pre-load rooms/bookings/blocks; stats computed from confirmed/checked-in/checked-out bookings with proportional revenue allocation when overlapping the window. Added 4 icons (arrow-left, bed, filter, pin) and CSS tokens (`--sh-pop`, `.input`, `.thin-scroll`). Verified end-to-end: `GET /dashboard/calendar` → 200, 4 seeded bookings render as pills linking to `tenant.bookings.show`, stats compute correctly. Fixed two bugs in original skeleton: `$booked->guest_name` referenced a non-existent column (now uses eager-loaded `guest:id,name`), and prev/today/next nav buttons were inert.
- **2026-05-02** — **Fix: `/dashboard` 500 error after login.** Filament 5.6's transitive `blade-ui-kit/blade-icons` was registering `<x-icon>` as a class component at boot, shadowing the project's local anonymous `resources/views/components/icon.blade.php` and throwing `SvgNotFound: "check"` on every dashboard render. Fix: published `config/blade-icons.php` with `components.default = null` so the bare `<x-icon>` name is freed for the local component; Filament's heroicons (prefixed `<x-heroicon-...>`) unaffected.
- **2026-05-01** — **Tenant UI redesign (Hauz visual system)** on branch `feat/dashboard-redesign` (pushed to GitHub). New sidebar+topbar shell in `layouts/app.blade.php`, design-system Blade components (`stat-card`, `pill`, `pro-lock`, `sparkline`, `property-visual`, `avatar`, `icon` + 14 icon partials). Full screen ports: Dashboard (Livewire wired to `StatisticsService`), Properties (index/create/show), Calendar (14-day grid), Bookings (index/show), Subscription, Integrations. New `Tenant\` controllers replace closure routes in `routes/web.php`. New `<x-app-layout>` thin-wrapper component coexists with old `@extends('layouts.app')` views (auth, marketplace, welcome) — both render through same shell. Routes verified via `php artisan route:list --name=tenant` (13 tenant routes). `npm run build` green. **Outstanding:** `PropertyController@store` left as TODO — wire against existing `Property` model + form-request rules before `POST /dashboard/properties` is functional.
- **2026-05-01** — **GitHub repo published** at `git@github.com:mnhidayatmat/homestay.git`. `main` branch holds full project scaffold (250 files, 24,118 LOC). `.gitignore` correctly excludes `.env`, `vendor/`, `node_modules/`, `public/build/`, IDE configs.
- **2026-05-01** — **Locale toggle (BM/EN)** in header. `SetLocale` middleware reads from session → cookie → `users.locale` → config. `LocaleController@switch` route at `/locale/{locale}` (whitelisted to `ms|en`) persists choice to all three. Empty `lang/en.json` since source strings are English. Toggle pill UI in top-right of `layouts/app.blade.php`. Verified working end-to-end (BM "Daftar" ↔ EN "Sign up").

### ✅ Completed (all 19 v1 phases, scaffold + skeleton level)
**Phase 1**: Laravel 12.58, packages installed (Sanctum, Livewire 4, Filament 5.6, Spatie Permission, Pennant, Intervention v4, DomPDF v3), CLAUDE.md
**Phase 2**: Multi-tenancy: `tenants`, `subscriptions`, `tenant_users`, `super_admins`, `audit_logs`, updated `users` table. `BelongsToTenant` trait + scope. `TenantContext` singleton + `SetTenantContext`/`RequireTenant` middleware. Pennant features (~19) defined. Spatie roles seeded (owner/manager/cleaner/laundry). `CreateTenantAndOwner` action.
**Phase 3**: Properties, rooms, photos, amenities, pricing rules, calendar blocks. `PricingEngine`. Properties Livewire CRUD UI.
**Phase 4**: Bookings + booking_guests + payments + payment_transactions + webhook_events. `AvailabilityService`, `CreateBooking` action, `ToyyibpayClient`, `ToyyibpayWebhookController` (signed callback verify + idempotency).
**Phase 5**: Invoices + invoice_templates + communications_log. `GenerateInvoice` action with PDF rendering via DomPDF. `BookingConfirmationMail`/`PaymentReminderMail`/`CheckInInstructionsMail` mailables. `WhatsAppService` (Business API stub + click-to-WA helper).
**Phase 6**: Cleaning, laundry, maintenance, inventory tables + models. `GenerateOperationalTasksForBooking` auto-generates tasks on booking confirmation (paid tier).
**Phase 7**: Reviews (polymorphic), guest_blacklist_entries, incident_reports. `SubmitGuestReview`, `ReportIncident` actions. Marketplace rating auto-refresh.
**Phase 8**: Marketplace_listings + commissions + payouts + disputes. `PublishListing` action with Pennant gate. `RunMonthlyCommissionPayout` job. Public marketplace search + show controllers and Blade views.
**Phase 9**: Channel_integrations table + model. `GoogleCalendarService` (OAuth URL, token exchange, push event).
**Phase 10**: `StatisticsService` (occupancy / ADR / revenue calculators).
**Phase 11**: Filament 5.6 super-admin panel at `/super-admin` with `super_admin` auth guard. **All 4 resources fully wired** (Tenant, Subscription, GuestBlacklistEntry, Dispute) — real form schemas with Section grouping + relationship selects, table columns with status/severity badges and money formatting, status filters, distinct nav icons, navigationSort. Login page rebranded with Claude cream/orange palette (`renderHook` injects branded styles + intro panel); BM/EN locale toggle in topbar; `SetLocale` middleware added to panel stack so the toggle actually flips Filament's locale.
**Phase 12**: Sanctum API at `/api/v1/*` — auth, me, properties, rooms, bookings, calendar, cleaning/laundry tasks, reports. Rate limiters wired (auth-login, auth-otp-send, marketplace-search, booking-create-public, api-read, api-write, webhook-toyyibpay, password-reset).
**Phase 13**: PWA manifest + service worker + i18n (`lang/ms.json` + empty `lang/en.json`) + **BM/EN header toggle** with session/cookie/user-record persistence.

### 🔨 What's intentionally skeleton (not production-ready)
The schema, services, actions, jobs, mailables, and routes are wired. The remaining work is mostly **API controllers + Livewire components + checkout-flow plumbing**:

1. **API V1 controllers**: only `AuthController` and `MeController` have full bodies. `Property/Room/Booking/Calendar/CleaningTask/LaundryTask/Report` controllers need handler methods filled in (returning JSON resources from already-built models).
2. **Livewire components**: only Properties index + form fleshed out. Need: rooms manager, calendar planner, bookings table, invoice template designer, cleaner/laundry task lists, reports dashboard.
3. ~~**Filament forms/tables**~~ — done 2026-05-03; all 4 super-admin resources have real form schemas + table columns + filters + distinct nav icons.
4. **Public booking page**: per-tenant slug page (`/{tenant_slug}/{property_slug}`) — Blade page for direct bookings not yet built.
5. **Marketplace booking flow**: search and listing-detail views fully redesigned 2026-05-03 (Booking Page web design + Wafa Mobile design, Alpine-driven 2-month calendar with real booked-date hatching from `Booking` table, live nights/total breakdown). Reserve CTA currently builds a `wa.me` link with prefilled message — full OTP → Toyyibpay redirect flow is v1.5.
6. **Email templates**: only `confirmation.blade.php` filled. `payment-reminder` and `checkin-instructions` are stub markdown.
7. **Tests**: no Pest/PHPUnit tests written. Critical to add: tenant isolation tests, RBAC tests, booking conflict tests, webhook signature tests.
8. **Seeders**: amenity master list not seeded (`AmenitySeeder` exists but empty). No demo-data seeder either — `MarketplaceListing::count() == 0` after `migrate:fresh --seed`, so the redesigned `/marketplace` page renders empty until you create properties + publish listings manually.
9. ~~**Frontend assets**~~ — done 2026-05-01; `npm run build` is part of the CI loop.

### 🚧 Blockers / TODO before going to staging
- [x] ~~Run `npm install && npm run build`~~ — done 2026-05-01
- [ ] Set up MySQL locally with proper credentials → switch `.env` from sqlite to mysql, run `php artisan migrate:fresh --seed`
- [ ] Provision AWS resources (SES, S3, RDS, ElastiCache) in `ap-southeast-1`
- [ ] Register Toyyibpay merchant + populate `TOYYIBPAY_*` env vars
- [ ] Register Google Cloud project + enable Calendar API + OAuth client
- [ ] Register Firebase project for FCM, save credentials to `storage/app/firebase-credentials.json`
- [ ] Apply for WhatsApp Business API
- [ ] PDPA-compliant privacy policy + ToS (lawyer-reviewed)
- [ ] MOTAC verification process — manual super-admin review or external API?
- [ ] Generate PNG icons for `public/icons/icon-192.png` and `icon-512.png` (PWA manifest)
- [ ] Decide: Pest vs PHPUnit (recommend Pest for v1 onwards)
- [ ] Write a `DemoDataSeeder` (tenant + property + rooms + a published `MarketplaceListing` + a few `Booking` rows) so `/marketplace` and the new public booking page have something to render after `migrate:fresh --seed`
- [ ] Add Malay (`lang/ms.json`) translations for the four super-admin Filament resource labels + Filament's own framework strings (or install a community Malay package) — currently the super-admin panel chrome stays English even when locale=ms

### 🗺️ Path to running locally (for the next session)
```bash
cd D:\App\Laravel\homestay
composer install               # if vendor/ stale
npm install
npm run build                  # one-time asset build
php artisan migrate:fresh --seed
php artisan serve              # http://localhost:8000
# In another terminal:
php artisan queue:work --queue=critical,email,default,low,pdf,sync
```
Default super-admin: `admin@homestaymy.com` / `ChangeMe123!` → login at `/super-admin`

---

## 📚 Design reference

The full system design lives in conversation history under `/sc:design` output. Key sections:
1. Multi-tenancy strategy (row-level)
2. Entity relationship model (~30 entities listed)
3. API surface for Flutter (full endpoint inventory)
4. Booking flow (direct vs marketplace) + commission split
5. Role/permission matrix
6. AWS deployment topology
7. Queue & scheduled job design (~20 jobs listed)
8. Security architecture
9. v1 scope boundary

If the conversation history is lost, regenerate with `/sc:design` using this file as input.
