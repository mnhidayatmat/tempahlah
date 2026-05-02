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

> Last updated: 2026-05-02 — Full prototype port (11 screens) on `feat/dashboard-redesign` branch. All sidebar items click through to working pages wired to real backend models.

### 📌 Update protocol
**Whenever a feature is implemented, fixed, or removed**, update this section the same turn — don't defer. Bump the "Last updated" date and add a one-line entry to the "Recently shipped" log below. Move from "skeleton" to "completed" as work is fleshed out. Move blockers off the TODO list as they're resolved.

### 🆕 Recently shipped (newest first)
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
**Phase 11**: Filament 5.6 super-admin panel at `/super-admin` with `super_admin` auth guard. Filament resources scaffolded for Tenant, Subscription, GuestBlacklistEntry, Dispute.
**Phase 12**: Sanctum API at `/api/v1/*` — auth, me, properties, rooms, bookings, calendar, cleaning/laundry tasks, reports. Rate limiters wired (auth-login, auth-otp-send, marketplace-search, booking-create-public, api-read, api-write, webhook-toyyibpay, password-reset).
**Phase 13**: PWA manifest + service worker + i18n (`lang/ms.json` + empty `lang/en.json`) + **BM/EN header toggle** with session/cookie/user-record persistence.

### 🔨 What's intentionally skeleton (not production-ready)
The schema, services, actions, jobs, mailables, and routes are wired. The remaining work is mostly **UI polish + filling in controller bodies + Filament forms/tables**:

1. **API V1 controllers**: only `AuthController` and `MeController` have full bodies. `Property/Room/Booking/Calendar/CleaningTask/LaundryTask/Report` controllers need handler methods filled in (returning JSON resources from already-built models).
2. **Livewire components**: only Properties index + form fleshed out. Need: rooms manager, calendar planner, bookings table, invoice template designer, cleaner/laundry task lists, reports dashboard.
3. **Filament forms/tables**: resources are generated but their `form()` and `table()` schemas need wiring to model fields.
4. **Public booking page**: per-tenant slug page (`/{tenant_slug}/{property_slug}`) — Blade page for direct bookings not yet built.
5. **Marketplace booking flow**: search and listing-detail views exist, but the full date-pick → checkout → OTP → Toyyibpay redirect flow is not wired.
6. **Email templates**: only `confirmation.blade.php` filled. `payment-reminder` and `checkin-instructions` are stub markdown.
7. **Tests**: no Pest/PHPUnit tests written. Critical to add: tenant isolation tests, RBAC tests, booking conflict tests, webhook signature tests.
8. **Seeders**: amenity master list not seeded (`AmenitySeeder` exists but empty).
9. **Frontend assets**: `npm install && npm run build` not run yet.

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
