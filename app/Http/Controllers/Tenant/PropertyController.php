<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Support\Billing\PlanLimits;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    public function index()
    {
        $properties = Property::query()
            ->with([
                // `beds` carries the bedroom count for whole-house properties
                // (where the single synthetic Room represents the whole place).
                // Without it, the card renders "0 bedrooms" — actual DB value
                // is correct, eager-load was just dropping the column.
                'rooms:id,property_id,base_price,beds',
                // For the cover image on each card. Minimal columns; the
                // view falls back to a gradient if no photo exists.
                'photos:id,property_id,path,disk,is_hero,sort_order',
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('tenant.properties.index', [
            'properties' => $properties,
            'tenant' => app(TenantContext::class)->current(),
        ]);
    }

    public function create()
    {
        // Pre-fill the property name with the homestay name the host entered at
        // registration (tenants.business_name) — for their very first property
        // it's almost always the same, so the setup-guide "Add homestay" step
        // starts filled in. Only used as a default; the host can change it.
        $tenant = app(TenantContext::class)->current();

        // Only for their very first homestay — a multi-property host adding a
        // second one shouldn't have the business name wrongly pre-filled.
        // Property::query() is auto-scoped to the current tenant (BelongsToTenant).
        $isFirstProperty = $tenant && ! Property::query()->exists();

        return view('tenant.properties.create', [
            'amenityGroups' => $this->amenityGroups(),
            'defaultName' => $isFirstProperty ? $tenant->business_name : null,
        ]);
    }

    public function store(Request $request)
    {
        // District (daerah) is submitted as `city` and must belong to the chosen
        // state — so the stored state/city always match the marketplace filter.
        $stateForRule = (string) $request->input('state');
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'address_line1' => 'required|string|max:160',
            'state' => ['required', 'string', Rule::in(array_keys(config('districts')))],
            'city' => ['required', 'string', 'max:80', Rule::in(config('districts.'.$stateForRule, []))],
            'postcode' => ['required', 'digits:5'],
            'pricing_mode' => 'nullable|in:whole_house,per_room',
            'bedrooms'   => 'required|integer|min:1|max:50',
            'bathrooms'  => 'nullable|integer|min:0|max:50',
            'toilets'    => 'nullable|integer|min:0|max:50',
            'max_guests' => 'nullable|integer|min:1|max:200',
            'base_price' => 'required|numeric|min:0|max:999999',
            'description' => 'nullable|string|max:2000',
            'amenities'   => 'nullable|array',
            'amenities.*' => 'integer|exists:amenities,id',
        ]);

        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $mode = $validated['pricing_mode'] ?? Property::PRICING_WHOLE_HOUSE;
        $bedrooms = (int) $validated['bedrooms'];

        // Plan property caps (config/homestay.php → plans): free 1, pro 3,
        // ultra unlimited.
        if (! PlanLimits::canAddProperty($tenant)) {
            return redirect()
                ->route('tenant.properties.index')
                ->with('error', __('Your :plan plan includes :n homestay(s). Upgrade for more homestays.', [
                    'plan' => \App\Support\Billing\Plans::name($tenant->planKey()),
                    'n' => PlanLimits::maxProperties($tenant),
                ]));
        }

        // A per-room property creates one Room per bedroom; a whole-house
        // property is always a single Room, so this only bites large per-room
        // setups on the Free plan.
        $requestedRooms = $mode === Property::PRICING_WHOLE_HOUSE ? 1 : $bedrooms;
        if (! PlanLimits::roomsAllowed($tenant, $requestedRooms)) {
            return back()
                ->withInput()
                ->with('error', __('Your :plan plan allows up to :n rooms per homestay. Upgrade to Pro for unlimited rooms.', [
                    'plan' => \App\Support\Billing\Plans::name($tenant->planKey()),
                    'n' => PlanLimits::maxRoomsPerProperty($tenant),
                ]));
        }
        // Sensible default for whole-house capacity: 2 guests per bedroom.
        $maxGuests = (int) ($validated['max_guests'] ?? max(2, $bedrooms * 2));

        $property = DB::transaction(function () use ($validated, $tenant, $mode, $bedrooms, $maxGuests) {
            $property = Property::create([
                'tenant_id' => $tenant->id,
                'public_id' => Str::ulid(),
                'slug' => $this->uniqueSlug($validated['name'], $tenant->id),
                'name' => $validated['name'],
                'address_line1' => $validated['address_line1'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postcode' => $validated['postcode'],
                'country' => 'MY',
                'bathrooms'    => (int) ($validated['bathrooms'] ?? 0),
                'toilets'      => (int) ($validated['toilets'] ?? 0),
                'pricing_mode' => $mode,
                'check_in_time' => '15:00',
                'check_out_time' => '11:00',
                'description_en' => $validated['description'] ?? null,
                // Live immediately. A draft property is invisible on the host's
                // own booking page (TenantHomeController filters on active) and
                // is rejected by StoreBookingRequest — so a host who created a
                // homestay and shared their link saw an empty page, and any guest
                // who reached the form was told "Selected property is not
                // available". There is no status field on the create form, so
                // nobody ever chose draft. They can still archive or unpublish
                // from the edit page.
                'status' => Property::STATUS_ACTIVE,
                // Sensible default — RM 100 booking fee = the "pay now"
                // amount on the public booking flow. Host can edit or
                // zero it from Property → Pricing → Booking fee.
                'booking_fee_amount' => 100.00,
            ]);

            if ($mode === Property::PRICING_WHOLE_HOUSE) {
                // ONE "Whole house" Room row carries the flat nightly rate.
                // Bookings still attach to a room_id, so the existing booking
                // engine, PricingEngine, AvailabilityService etc. keep working
                // unchanged — the room just represents the whole property.
                Room::create([
                    'tenant_id' => $tenant->id,
                    'property_id' => $property->id,
                    'public_id' => Str::ulid(),
                    'name' => __('Whole house'),
                    'room_type' => 'entire_place',
                    'max_adults' => $maxGuests,
                    'max_children' => 0,
                    'beds' => $bedrooms,
                    'base_price' => $validated['base_price'],
                    'currency' => 'MYR',
                    'sst_applicable' => true,
                    'status' => 'active',
                ]);
            } else {
                // Per-room: N individual rooms, each with the same starting
                // rate. Tenant can later adjust per-room prices on the edit
                // page.
                for ($i = 1; $i <= $bedrooms; $i++) {
                    Room::create([
                        'tenant_id' => $tenant->id,
                        'property_id' => $property->id,
                        'public_id' => Str::ulid(),
                        'name' => __('Room :n', ['n' => $i]),
                        'room_type' => 'standard',
                        'max_adults' => 2,
                        'beds' => 1,
                        'base_price' => $validated['base_price'],
                        'currency' => 'MYR',
                        'sst_applicable' => true,
                        'status' => 'active',
                    ]);
                }
            }

            if (! empty($validated['amenities'])) {
                $property->amenities()->sync($validated['amenities']);
            }

            return $property;
        });

        $message = $mode === Property::PRICING_WHOLE_HOUSE
            ? __('Property ":name" created. Charging RM :p / night for the whole house.', [
                'name' => $property->name,
                'p' => number_format((float) $validated['base_price'], 0),
            ])
            : __('Property ":name" created with :n bookable room(s).', [
                'name' => $property->name,
                'n' => $bedrooms,
            ]);

        // Deliberately NOT auto-listing on the marketplace here. This call was a
        // no-op while new properties were drafts (autoListMarketplace returns
        // early on anything but active), and now that they're created live it
        // would publish a homestay to tempahlah.com before the host has added a
        // single photo. Listing still happens on the first save from the edit
        // page — exactly when it did before, since that's where the host used to
        // flip the property to active.

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id])
            ->with('status', $message);
    }

    /**
     * Auto-publish a homestay to the tempahlah.com marketplace once it's live,
     * unless the host has explicitly opted out. Idempotent — also keeps an
     * existing listing's denormalized fields fresh. Fails soft.
     */
    protected function autoListMarketplace(Property $property): void
    {
        $property->loadMissing('rooms');

        if ($property->status !== Property::STATUS_ACTIVE || $property->marketplace_opt_out) {
            return;
        }

        try {
            app(\App\Actions\Marketplace\PublishListing::class)->execute($property);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function edit(Property $property)
    {
        $property->load(['rooms' => fn ($q) => $q->orderBy('name'), 'amenities:id']);

        return view('tenant.properties.edit', [
            'property' => $property,
            'baseRate' => (float) ($property->rooms->min('base_price') ?? 0),
            'amenityGroups' => $this->amenityGroups(),
            'selectedAmenityIds' => $property->amenities->pluck('id')->all(),
        ]);
    }

    /**
     * Inline-edit just the stay policies (check-in/out times + house rules
     * + cancellation policy) from the property show page's Policies tab.
     * Skinnier than update() so it doesn't require re-submitting unrelated
     * fields like address/amenities.
     */
    public function updatePolicies(Request $request, Property $property)
    {
        $validated = $request->validate([
            'check_in_time'       => 'required|date_format:H:i',
            'check_out_time'      => 'required|date_format:H:i',
            'house_rules'         => 'nullable|string|max:1000',
            'cancellation_policy' => 'nullable|string|max:1000',
        ]);

        // cancellation_policy is NOT NULL on the table.
        if (array_key_exists('cancellation_policy', $validated)
            && $validated['cancellation_policy'] === null) {
            $validated['cancellation_policy'] = 'flexible';
        }

        $property->fill($validated)->save();

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id, 'tab' => 'policies'])
            ->with('status', __('Stay policies updated.'));
    }

    public function update(Request $request, Property $property)
    {
        // District (daerah) → `city`, must belong to the chosen state (see store()).
        $stateForRule = (string) $request->input('state');
        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'state'          => ['required', 'string', Rule::in(array_keys(config('districts')))],
            'city'           => ['required', 'string', 'max:80', Rule::in(config('districts.'.$stateForRule, []))],
            'postcode'       => ['required', 'digits:5'],
            'address_line1'  => 'required|string|max:160',
            'address_line2'  => 'nullable|string|max:160',
            // Optional pre-pinned map URL. Accept the common public-share
            // domains only — google.com/maps, maps.google.*, maps.app.goo.gl,
            // goo.gl/maps, waze.com — so we don't open arbitrary URLs from
            // the "Direction" button. Falsy/blank → fall back to address.
            'map_url'        => ['nullable', 'url', 'max:500', 'regex:/^https:\/\/(www\.|maps\.)?(google\.[a-z.]+\/maps|google\.[a-z.]+\/maps\/|maps\.app\.goo\.gl|goo\.gl\/maps|waze\.com)\b/i'],
            // NOTE: booking_fee_amount + booking_fee_label moved to the
            // Pricing tab; see updateFee() below.
            'bathrooms'      => 'nullable|integer|min:0|max:50',
            'toilets'        => 'nullable|integer|min:0|max:50',
            // Whole-house: bedroom count lives on the single room's `beds`
            // column (mirrors store()). Per-room: bedrooms == room count,
            // managed via rooms, so the field isn't shown there.
            'bedrooms'       => 'nullable|integer|min:1|max:50',
            'pricing_mode'   => 'nullable|in:whole_house,per_room',
            'max_guests'     => 'nullable|integer|min:1|max:200',
            'default_guests' => 'nullable|integer|min:1|max:200',
            'description_en' => 'nullable|string|max:2000',
            'description_bm' => 'nullable|string|max:2000',
            'check_in_time'  => 'required|date_format:H:i',
            'check_out_time' => 'required|date_format:H:i',
            'status'         => 'required|in:draft,active,archived',
            'base_price'     => 'nullable|numeric|min:0|max:999999',
            'house_rules'    => 'nullable|string|max:1000',
            'cancellation_policy' => 'nullable|string|max:1000',
            'amenities'      => 'nullable|array',
            'amenities.*'    => 'integer|exists:amenities,id',
        ]);

        $newBase = $request->filled('base_price') ? (float) $validated['base_price'] : null;
        unset($validated['base_price']);

        // max_guests is meaningful only for whole-house mode (single room).
        $newMaxGuests = $request->filled('max_guests') ? (int) $validated['max_guests'] : null;
        unset($validated['max_guests']);

        // bedrooms is stored on the whole-house room's `beds` column, not on
        // the property — pull it out of the property fill.
        $newBedrooms = $request->filled('bedrooms') ? (int) $validated['bedrooms'] : null;
        unset($validated['bedrooms']);

        // The properties table has NOT NULL columns (city/state/postcode/cancellation_policy)
        // but Laravel's ConvertEmptyStringsToNull middleware turns blank inputs into nulls.
        // Coerce them back to empty strings (or the column default) so save() doesn't trip the constraint.
        foreach (['city', 'state', 'postcode', 'cancellation_policy'] as $k) {
            if (array_key_exists($k, $validated) && $validated[$k] === null) {
                $validated[$k] = $k === 'cancellation_policy' ? 'flexible' : '';
            }
        }

        $amenityIds = $validated['amenities'] ?? [];
        unset($validated['amenities']);

        DB::transaction(function () use ($property, $validated, $newBase, $newMaxGuests, $newBedrooms, $amenityIds) {
            $property->fill($validated)->save();
            if ($newBase !== null) {
                // Same flat rate applies to every room (1 in whole-house mode,
                // N in per-room mode).
                $property->rooms()->update(['base_price' => $newBase]);
            }
            if ($newMaxGuests !== null && $property->isWholeHousePricing()) {
                // Only the single "Whole house" room exists — update its capacity.
                $property->rooms()->update(['max_adults' => $newMaxGuests]);
            }
            if ($newBedrooms !== null && $property->isWholeHousePricing()) {
                // Bedroom count lives on the single "Whole house" room's `beds`.
                $property->rooms()->update(['beds' => $newBedrooms]);
            }
            $property->amenities()->sync($amenityIds);
        });

        // Auto-list once live (opt-out), and keep an existing listing's
        // denormalized fields in step with the edited property.
        $this->autoListMarketplace($property->fresh('rooms'));

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', __('Homestay ":name" updated.', ['name' => $property->name]));
    }

    /**
     * Opt a homestay into the public tempahlah.com marketplace (Pro feature).
     * PublishListing enforces the plan gate + active-status requirement.
     */
    public function publishMarketplace(Property $property, \App\Actions\Marketplace\PublishListing $action)
    {
        // Clear any prior opt-out, then list.
        $property->update(['marketplace_opt_out' => false]);

        try {
            $action->execute($property);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('":name" is now listed on the Tempahlah marketplace.', ['name' => $property->name]));
    }

    /** Remove a homestay from the public marketplace (keeps direct booking working). */
    public function unpublishMarketplace(Property $property, \App\Actions\Marketplace\PublishListing $action)
    {
        // Opt out so it isn't silently re-listed on the next edit.
        $property->update(['marketplace_opt_out' => true]);
        $action->unpublish($property);

        return back()->with('status', __('":name" has been removed from the marketplace.', ['name' => $property->name]));
    }

    /**
     * Dedicated endpoint for the "Booking fee" card on the Pricing tab.
     * Single-field UX — amount only. The label is FIXED to "Booking fee"
     * (EN) / "Yuran tempahan" (BM), resolved at render time based on
     * the viewer's locale via __('Booking fee'). We persist `null` for
     * booking_fee_label so the locale fallback in invoices / public
     * page / agent always kicks in.
     *
     * Empty/0 amount → both columns nulled; the fee row disappears from
     * the public summary, invoice line items, agent quote, etc.
     */
    public function updateFee(Request $request, Property $property)
    {
        $validated = $request->validate([
            'booking_fee_amount' => 'nullable|numeric|min:0|max:9999.99',
        ]);

        $amount = $validated['booking_fee_amount'] ?? null;
        if ($amount === null || (float) $amount <= 0) {
            $property->booking_fee_amount = null;
            $property->booking_fee_label  = null;
        } else {
            $property->booking_fee_amount = round((float) $amount, 2);
            // Always null on the column — the human label is resolved at
            // render time so it follows the viewer's locale (not the
            // host's at save time).
            $property->booking_fee_label  = null;
        }
        $property->save();

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id, 'tab' => 'pricing'])
            ->with('status', $property->booking_fee_amount
                ? __('Booking fee saved: RM :amt', [
                    'amt' => number_format((float) $property->booking_fee_amount, 2),
                ])
                : __('Booking fee removed.'));
    }

    public function destroy(Property $property)
    {
        $blockingBookings = \App\Models\Booking::query()
            ->where('property_id', $property->id)
            ->whereIn('status', [
                \App\Models\Booking::STATUS_PENDING,
                \App\Models\Booking::STATUS_CONFIRMED,
                \App\Models\Booking::STATUS_CHECKED_IN,
            ])
            ->where('check_out', '>=', now()->startOfDay())
            ->count();

        if ($blockingBookings > 0) {
            return back()->with('error', __('Cannot delete — :n active or upcoming booking(s) on this property. Cancel them first.', ['n' => $blockingBookings]));
        }

        $name = $property->name;
        DB::transaction(function () use ($property) {
            $property->rooms()->delete();
            $property->delete();
        });

        // Redirect back to wherever Delete was clicked from (settings page,
        // properties index, or show page). If we can't infer the referrer
        // (e.g. direct API call) fall back to the properties index — the
        // canonical "list of homestays" view.
        $referer = url()->previous();
        $fallback = route('tenant.properties.index');
        $target = str_contains($referer, '/dashboard/properties/'.$property->public_id)
            ? $fallback  // came from the now-deleted show/edit page, can't go back there
            : ($referer ?: $fallback);

        return redirect($target)
            ->with('status', __('Homestay ":name" deleted.', ['name' => $name]));
    }

    public function show($id, Request $request)
    {
        $property = Property::with([
                'rooms' => fn ($q) => $q->orderBy('name'),
                'tenant', 'amenities', 'photos',
                'pricingRules' => fn ($q) => $q->orderByDesc('active')->orderBy('priority'),
            ])
            ->findOrFail($id);

        $tab = in_array($request->query('tab'), ['rooms', 'pricing', 'facilities', 'policies', 'photos'], true)
            ? $request->query('tab')
            : 'rooms';

        // Occupancy + rate stats for last 30 days
        $bookings = \App\Models\Booking::query()
            ->where('property_id', $property->id)
            ->whereIn('status', [
                \App\Models\Booking::STATUS_CONFIRMED,
                \App\Models\Booking::STATUS_CHECKED_IN,
                \App\Models\Booking::STATUS_CHECKED_OUT,
            ])
            ->where('check_in', '>=', now()->subDays(30))
            ->get(['nights', 'total_amount']);

        $nights = (int) $bookings->sum('nights');
        $available = max(1, $property->rooms->count() * 30);
        $occupancy = $nights > 0 ? round(($nights / $available) * 100) : 0;
        $startingRate = (float) ($property->rooms->min('base_price') ?? 0);

        return view('tenant.properties.show', [
            'property' => $property,
            'tab' => $tab,
            'occupancy' => $occupancy,
            'startingRate' => $startingRate,
            'rating' => $property->rating ?? '—',
            'amenityGroups' => $this->amenityGroups(),
        ]);
    }

    protected function uniqueSlug(string $name, int $tenantId): string
    {
        $base = Str::slug($name) ?: 'property';
        $slug = $base;
        $i = 0;

        while (Property::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Amenities grouped by category for the property form.
     * Returns: [categoryKey => ['label' => '...', 'items' => Collection<Amenity>]]
     */
    protected function amenityGroups(): array
    {
        $categories = [
            'essential'     => __('Essentials'),
            'kitchen'       => __('Kitchen'),
            'entertainment' => __('Entertainment'),
            'outdoor'       => __('Outdoor & recreation'),
            'family'        => __('Family & accessibility'),
            'cultural'      => __('Cultural & religious'),
            'safety'        => __('Safety'),
            'workspace'     => __('Workspace'),
        ];

        $items = Amenity::orderBy('sort_order')->get();
        $groups = [];
        foreach ($categories as $key => $label) {
            $groupItems = $items->where('category', $key)->values();
            if ($groupItems->isNotEmpty()) {
                $groups[$key] = ['label' => $label, 'items' => $groupItems];
            }
        }

        return $groups;
    }
}
