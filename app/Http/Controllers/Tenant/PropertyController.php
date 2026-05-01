<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Support\Tenancy\TenantContext;

class PropertyController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $properties = collect();
        if (class_exists(Property::class)) {
            $properties = Property::query()
                ->when(method_exists(Property::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
                ->orderByDesc('created_at')
                ->get();
        }
        return view('tenant.properties.index', compact('properties', 'tenant'));
    }

    public function create()
    {
        return view('tenant.properties.create');
    }

    public function show($id)
    {
        $tenant = app(TenantContext::class)->current();
        $property = Property::query()
            ->when(method_exists(Property::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
            ->findOrFail($id);
        return view('tenant.properties.show', compact('property'));
    }
}
