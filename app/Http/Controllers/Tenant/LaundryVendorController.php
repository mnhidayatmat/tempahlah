<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\LaundryVendor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class LaundryVendorController extends Controller
{
    public function index()
    {
        $vendors = LaundryVendor::withCount('laundryTasks')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('tenant.laundry-vendors.index', [
            'vendors' => $vendors,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
        ]);

        LaundryVendor::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('tenant.laundry-vendors.index')
            ->with('status', __('Vendor added.'));
    }

    public function update(Request $request, int $id)
    {
        $vendor = LaundryVendor::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'is_active' => 'nullable|boolean',
        ]);

        $vendor->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()
            ->route('tenant.laundry-vendors.index')
            ->with('status', __('Vendor updated.'));
    }

    public function destroy(int $id)
    {
        LaundryVendor::findOrFail($id)->delete();

        return redirect()
            ->route('tenant.laundry-vendors.index')
            ->with('status', __('Vendor removed.'));
    }
}
