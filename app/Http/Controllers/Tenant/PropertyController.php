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
            ->with(['rooms:id,property_id,base_price'])
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
            'bedrooms' => 'required|integer|min:1|max:50',
            'bathrooms' => 'nullable|integer|min:0|max:50',
            'toilets'   => 'nullable|integer|min:0|max:50',
            'base_price' => 'required|numeric|min:0|max:999999',
            'description' => 'nullable|string|max:2000',
            'amenities'   => 'nullable|array',
            'amenities.*' => 'integer|exists:amenities,id',
        ]);

        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $property = DB::transaction(function () use ($validated, $tenant) {
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
                'bathrooms' => (int) ($validated['bathrooms'] ?? 0),
                'toilets'   => (int) ($validated['toilets'] ?? 0),
                'check_in_time' => '15:00',
                'check_out_time' => '11:00',
                'description_en' => $validated['description'] ?? null,
                'status' => Property::STATUS_DRAFT,
            ]);

            for ($i = 1; $i <= (int) $validated['bedrooms']; $i++) {
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

            if (! empty($validated['amenities'])) {
                $property->amenities()->sync($validated['amenities']);
            }

            return $property;
        });

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id])
            ->with('status', __('Property ":name" created with :n room(s).', [
                'name' => $property->name,
                'n' => $validated['bedrooms'],
            ]));
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

    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'city'           => 'nullable|string|max:80',
            'state'          => 'nullable|string|max:80',
            'postcode'       => 'nullable|string|max:16',
            'address_line1'  => 'required|string|max:160',
            'address_line2'  => 'nullable|string|max:160',
            'bathrooms'      => 'nullable|integer|min:0|max:50',
            'toilets'        => 'nullable|integer|min:0|max:50',
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

        DB::transaction(function () use ($property, $validated, $newBase, $amenityIds) {
            $property->fill($validated)->save();
            if ($newBase !== null) {
                $property->rooms()->update(['base_price' => $newBase]);
            }
            $property->amenities()->sync($amenityIds);
        });

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', __('Homestay ":name" updated.', ['name' => $property->name]));
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

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', __('Homestay ":name" deleted.', ['name' => $name]));
    }

    public function show($id, Request $request)
    {
        $property = Property::with(['rooms' => fn ($q) => $q->orderBy('name'), 'tenant', 'amenities'])
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
