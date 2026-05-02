<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
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
            ->orderByDesc('created_at')
            ->get();

        return view('tenant.properties.index', [
            'properties' => $properties,
            'tenant' => app(TenantContext::class)->current(),
        ]);
    }

    public function create()
    {
        return view('tenant.properties.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'city' => 'nullable|string|max:80',
            'address_line1' => 'required|string|max:160',
            'bedrooms' => 'required|integer|min:1|max:50',
            'base_price' => 'required|numeric|min:0|max:999999',
            'description' => 'nullable|string|max:2000',
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

            return $property;
        });

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id])
            ->with('status', __('Property ":name" created with :n room(s).', [
                'name' => $property->name,
                'n' => $validated['bedrooms'],
            ]));
    }

    public function show($id)
    {
        $property = Property::with(['rooms' => fn ($q) => $q->orderBy('name')])
            ->findOrFail($id);

        return view('tenant.properties.show', compact('property'));
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
}
