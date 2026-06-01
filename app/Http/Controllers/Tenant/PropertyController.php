<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        return view('tenant.properties.create', [
            'amenityGroups' => $this->amenityGroups(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'city' => 'nullable|string|max:80',
            'address_line1' => 'required|string|max:160',
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
        // Sensible default for whole-house capacity: 2 guests per bedroom.
        $maxGuests = (int) ($validated['max_guests'] ?? max(2, $bedrooms * 2));

        $property = DB::transaction(function () use ($validated, $tenant, $mode, $bedrooms, $maxGuests) {
            $property = Property::create([
                'tenant_id' => $tenant->id,
                'public_id' => Str::ulid(),
                'slug' => $this->uniqueSlug($validated['name'], $tenant->id),
                'name' => $validated['name'],
                'address_line1' => $validated['address_line1'],
                'city' => $validated['city'] ?? '',
                'state' => '',
                'postcode' => '',
                'country' => 'MY',
                'bathrooms'    => (int) ($validated['bathrooms'] ?? 0),
                'toilets'      => (int) ($validated['toilets'] ?? 0),
                'pricing_mode' => $mode,
                'check_in_time' => '15:00',
                'check_out_time' => '11:00',
                'description_en' => $validated['description'] ?? null,
                'status' => Property::STATUS_DRAFT,
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

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id])
            ->with('status', $message);
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
        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'city'           => 'nullable|string|max:80',
            'state'          => 'nullable|string|max:80',
            'postcode'       => 'nullable|string|max:16',
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

        DB::transaction(function () use ($property, $validated, $newBase, $newMaxGuests, $amenityIds) {
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
            $property->amenities()->sync($amenityIds);
        });

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', __('Homestay ":name" updated.', ['name' => $property->name]));
    }

    /**
     * Dedicated endpoint for the "Per-booking flat fee" card on the
     * Pricing tab. Separate from update() so the Pricing tab can save
     * the fee without re-validating every field on the general edit
     * form (name, address, policies, etc.).
     *
     * Normalisation: empty/0 amount → BOTH columns are nulled so we
     * never persist an orphan label. Non-zero amount with a missing
     * label → defaults to the translated "Booking fee" / "Yuran
     * tempahan".
     */
    public function updateFee(Request $request, Property $property)
    {
        $validated = $request->validate([
            'booking_fee_amount' => 'nullable|numeric|min:0|max:9999.99',
            'booking_fee_label'  => 'nullable|string|max:80',
        ]);

        $amount = $validated['booking_fee_amount'] ?? null;
        if ($amount === null || (float) $amount <= 0) {
            $property->booking_fee_amount = null;
            $property->booking_fee_label  = null;
        } else {
            $property->booking_fee_amount = round((float) $amount, 2);
            $property->booking_fee_label  = trim((string) ($validated['booking_fee_label'] ?? '')) ?: __('Booking fee');
        }
        $property->save();

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id, 'tab' => 'pricing'])
            ->with('status', $property->booking_fee_amount
                ? __('Booking fee saved: :label · RM :amt', [
                    'label' => $property->booking_fee_label,
                    'amt'   => number_format((float) $property->booking_fee_amount, 2),
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
